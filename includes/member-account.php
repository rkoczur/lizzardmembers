<?php
/**
 * Egyéni folyószámla — tagonként mennyit kellett volna fizetnie és mennyit fizetett be.
 * Kizárólag a túra-részvételi díjakat tartja nyilván; a tagdíj nem része a folyószámlának.
 *
 * A tranzakció és a tag összekapcsolása a `transactions.partner` mező alapján történik
 * (ugyanaz a minta, mint a `recalcMembershipPayments()`-nél): partner = "Vezetéknév Keresztnév".
 * A túrához tartozó befizetést a tranzakció `event_type`/`event_id` mezője köti a túrához.
 */

/** SQL: a tag teljes neve úgy, ahogy a tranzakciók `partner` mezőjében szerepel. */
function memberFullNameSql(string $alias = 'u'): string
{
    return "TRIM(CONCAT(COALESCE($alias.lastname,''), ' ', COALESCE($alias.firstname,'')))";
}

/**
 * Egy tag folyószámlája: túránként az előírt és a ténylegesen befizetett részvételi díj.
 *
 * A `rows` az előírás–befizetés párokat tartalmazza (ezekből áll az egyenleg), az `unassigned`
 * pedig azokat az idei befizetéseket, amelyekhez nem tartozik előírás — ezek nem terhelik az
 * egyenleget, mert nem tudni, mihez tartoznak (tipikusan túrához nem rendelt tranzakció).
 * A már rögzített túrához (`event_type = 'tour'`) kötött befizetés nem kerül az `unassigned` közé:
 * az ilyen túra sosem volt meghirdetett túra, így nincs hozzá jelentkezés és előírás sem.
 *
 * @return array{rows: array, unassigned: array, charged: float, paid: float, balance: float, unassigned_total: float}
 *         balance < 0 → tartozás, balance > 0 → túlfizetés
 */
function getMemberAccount(PDO $pdo, int $userId): array
{
    $empty = ['rows' => [], 'unassigned' => [], 'charged' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'unassigned_total' => 0.0];

    $stmt = $pdo->prepare("SELECT id, lastname, firstname, COALESCE(level,1) AS level, COALESCE(role,'user') AS role FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return $empty;

    $fullName = trim(($user['lastname'] ?? '') . ' ' . ($user['firstname'] ?? ''));
    if ($fullName === '') return $empty;

    $year     = (int)date('Y');
    $discount = getTourFeeDiscount((int)$user['level'], (string)$user['role']);

    // Előírás: megerősített jelentkezések díjas, nem törölt túrákra
    $tourStmt = $pdo->prepare("
        SELECT ft.id, ft.name, ft.start_date, ft.participation_fee, fta.fee_override
        FROM future_tour_applications fta
        JOIN future_tours ft ON ft.id = fta.future_tour_id
        WHERE fta.user_id = ? AND fta.status = 'confirmed'
          AND ft.status <> 'cancelled' AND COALESCE(ft.participation_fee, 0) > 0
        ORDER BY ft.start_date ASC
    ");
    $tourStmt->execute([$userId]);

    $tourRows = [];
    foreach ($tourStmt->fetchAll() as $t) {
        $tourRows[(int)$t['id']] = [
            'kind'    => 'tour',
            'label'   => 'Részvételi díj – ' . $t['name'],
            'date'    => $t['start_date'],
            'tour_id' => (int)$t['id'],
            'charged' => getApplicationFee((float)$t['participation_fee'], $discount, $t['fee_override']),
            'paid'    => 0.0,
        ];
    }

    // Befizetések besorolása
    $txStmt = $pdo->prepare("
        SELECT id, tx_date, category, description, amount, event_type, event_id
        FROM transactions
        WHERE tx_type = 'income' AND partner = ? COLLATE utf8mb4_unicode_ci
        ORDER BY tx_date ASC, id ASC
    ");
    $txStmt->execute([$fullName]);

    $unassigned = [];
    foreach ($txStmt->fetchAll() as $tx) {
        $amount = (float)$tx['amount'];
        $tourId = $tx['event_type'] === 'future_tour' ? (int)$tx['event_id'] : 0;
        $txYear = (int)substr((string)$tx['tx_date'], 0, 4);

        if ($tourId && isset($tourRows[$tourId])) {
            $tourRows[$tourId]['paid'] += $amount;
        } elseif ($tx['category'] === 'Tagdíj') {
            continue; // a tagdíj nem része a folyószámlának
        } elseif ($tx['event_type'] === 'tour' && $tx['event_id']) {
            // Már rögzített (sosem meghirdetett) túrához rendelt befizetés — ehhez nincs
            // jelentkezés, így előírás sem: nem hiányzó összerendelés, hanem lezárt tétel
            continue;
        } elseif ($txYear === $year) {
            // Nincs hozzá előírás — külön listában, az egyenlegen kívül
            $unassigned[] = [
                'tx_id'    => (int)$tx['id'],
                'date'     => $tx['tx_date'],
                'category' => $tx['category'],
                'label'    => $tx['description'],
                'amount'   => $amount,
            ];
        }
        // korábbi évek tételei: lezárt évhez tartoznak, nem terhelik az idei egyenleget
    }

    $rows = array_values($tourRows);
    $rows = array_values(array_filter($rows, fn($r) => $r['charged'] > 0 || $r['paid'] > 0));
    foreach ($rows as &$r) { $r['diff'] = round($r['paid'] - $r['charged'], 2); }
    unset($r);

    $charged = round(array_sum(array_column($rows, 'charged')), 2);
    $paid    = round(array_sum(array_column($rows, 'paid')), 2);

    return [
        'rows'             => $rows,
        'unassigned'       => $unassigned,
        'charged'          => $charged,
        'paid'             => $paid,
        'balance'          => round($paid - $charged, 2),
        'unassigned_total' => round(array_sum(array_column($unassigned, 'amount')), 2),
    ];
}

/**
 * Aktív tagok folyószámlájának összegzése (a Könyvelés „Folyószámlák” füléhez).
 * Csak az „Aktív” tagsági státuszúak szerepelnek („Tagdíj elmaradás” és „Inaktív” nem).
 * A `last_payment` frissességéért a hívó felel — előtte futtasd a `recalcMembershipPayments()`-t.
 *
 * @return array<int, array{user: array, charged: float, paid: float, balance: float, unassigned_total: float}>
 */
function getAllMemberAccounts(PDO $pdo): array
{
    $users = $pdo->query("
        SELECT id, lastname, firstname, email, last_payment, COALESCE(level,1) AS level
        FROM users WHERE active = 1
        ORDER BY lastname ASC, firstname ASC
    ")->fetchAll();

    $out = [];
    foreach ($users as $u) {
        // Csak az „Aktív” tagsági státuszúak kerülnek a folyószámla-listára
        if (getMemberStatus($u['last_payment'] ?? null) !== 'active') continue;
        $acc = getMemberAccount($pdo, (int)$u['id']);
        $out[] = [
            'user'             => $u,
            'charged'          => $acc['charged'],
            'paid'             => $acc['paid'],
            'balance'          => $acc['balance'],
            'unassigned_total' => $acc['unassigned_total'],
        ];
    }
    return $out;
}

/**
 * Túrához rendelt részvételi díj tranzakciók → jelentkezők fizetési státusza.
 *
 * Minden jelentkezőhöz összegzi a hozzá tartozó, az adott túrához rendelt bevételi
 * tranzakciókat (tagnál a teljes név, vendégnél a megadott név alapján), és ahol van
 * ilyen befizetés, ott beállítja a `paid_at` mezőt az első befizetés dátumára.
 * A `paid_at`-ot soha nem törli — a kézi jelölést nem írja felül.
 *
 * @param  int|null $tourId  Egy túra azonosítója, vagy null = minden túra
 * @return array<int, array{paid: float, first_date: string}>  jelentkezés-azonosító => befizetés
 */
function syncTourPaymentsFromTransactions(PDO $pdo, ?int $tourId = null): array
{
    $nameSql = memberFullNameSql('u');
    $sql = "
        SELECT fta.id AS app_id, SUM(t.amount) AS paid, MIN(t.tx_date) AS first_date
        FROM future_tour_applications fta
        LEFT JOIN users u ON u.id = fta.user_id
        JOIN transactions t
             ON t.tx_type = 'income'
            AND t.event_type = 'future_tour'
            AND t.event_id = fta.future_tour_id
            AND t.partner = COALESCE(NULLIF($nameSql, ''), fta.guest_name) COLLATE utf8mb4_unicode_ci
        WHERE fta.status <> 'cancelled'
    ";
    $params = [];
    if ($tourId !== null) { $sql .= " AND fta.future_tour_id = ?"; $params[] = $tourId; }
    $sql .= " GROUP BY fta.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $payments = [];
    $ids      = [];
    foreach ($stmt->fetchAll() as $row) {
        $payments[(int)$row['app_id']] = ['paid' => (float)$row['paid'], 'first_date' => (string)$row['first_date']];
        $ids[] = (int)$row['app_id'];
    }
    if (!$ids) return $payments;

    // Fizetettre állítás ott, ahol még nincs bejelölve
    $upd = $pdo->prepare("UPDATE future_tour_applications SET paid_at = ? WHERE id = ? AND paid_at IS NULL");
    foreach ($payments as $appId => $p) {
        $upd->execute([$p['first_date'] . ' 00:00:00', $appId]);
    }
    return $payments;
}
