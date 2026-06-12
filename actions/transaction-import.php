<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/bookkeeping-schema.php';
requireLogin();
if (!canManageFinances()) { flash('error', 'Nincs jogosultságod ehhez a művelethez.'); header('Location: ' . BASE_URL . '/admin/index.php'); exit; }
verifyCsrf();

$redirectBack = BASE_URL . '/admin/bookkeeping-import.php';
$step = trim($_POST['step'] ?? 'validate');

/** Típus felismerése a CSV szövegéből. */
function tx_parse_type(string $s): ?string
{
    $s = mb_strtolower(trim($s));
    if (in_array($s, ['bevetel', 'bevétel', 'income', 'be', 'b', '+'], true))  return 'income';
    if (in_array($s, ['kiadas', 'kiadás', 'expense', 'ki', 'k', '-'], true))    return 'expense';
    return null;
}

/** Összeg-szöveg számmá alakítása (szóköz/ezres/tizedes kezelése). */
function tx_parse_amount(string $s): ?float
{
    $s = trim(str_replace(["\xc2\xa0", ' ', 'Ft', 'ft'], '', $s));
    if ($s === '') return null;
    if (str_contains($s, ',') && !str_contains($s, '.')) {
        $s = str_replace(',', '.', $s);          // tizedes vessző
    } else {
        $s = str_replace(',', '', $s);           // ezres elválasztó vessző
    }
    if (!is_numeric($s)) return null;
    return (float)$s;
}

// ── CONFIRM: a munkamenetben tárolt, ellenőrzött sorok beszúrása ──────────────
if ($step === 'confirm') {
    $preview = $_SESSION['tx_import_preview'] ?? null;
    if (!$preview || empty($preview['rows'])) {
        flash('error', 'Nincs érvényes előnézeti adat. Töltsd fel újra a fájlt.');
        header('Location: ' . $redirectBack);
        exit;
    }

    $pdo = getDb();
    ensureBookkeepingSchema($pdo);

    $insert = $pdo->prepare("INSERT INTO transactions
        (tx_date, tx_type, category, description, event_type, event_id, event_label, partner, amount, account, invoice_number, created_by)
        VALUES (?,?,?,?,NULL,NULL,?,?,?,?,?,?)");
    $presetInsert = $pdo->prepare("INSERT IGNORE INTO transaction_presets (preset_type, value) VALUES (?, ?)");

    // Aktív tagok nevei — ezeket nem vesszük fel külön partner-presetként (a választóban már „Tagok” csoportban szerepelnek)
    $memberNames = $pdo->query("
        SELECT TRIM(CONCAT(COALESCE(lastname,''), ' ', COALESCE(firstname,''))) AS full_name
        FROM users WHERE active = 1 HAVING full_name <> ''
    ")->fetchAll(PDO::FETCH_COLUMN);
    $memberNameSet = array_fill_keys(array_map('mb_strtolower', $memberNames), true);

    $imported = 0;
    foreach ($preview['rows'] as $r) {
        $insert->execute([
            $r['tx_date'], $r['tx_type'], $r['category'], $r['description'],
            $r['event_label'], $r['partner'], $r['amount'], $r['account'], $r['invoice_number'],
            getCurrentUserId(),
        ]);
        $id = (int)$pdo->lastInsertId();

        // Új kategória / partner / számla automatikus felvétele az előre definiált értékek közé
        $presetInsert->execute(['category', $r['category']]);
        if (!isset($memberNameSet[mb_strtolower($r['partner'])])) {
            $presetInsert->execute(['partner', $r['partner']]);
        }
        $presetInsert->execute(['account',  $r['account']]);

        logAudit($pdo, 'create', 'transaction', $id,
            transactionAuditLabel($r['tx_date'], $r['tx_type'], $r['category'], $r['amount']),
            [['k' => 'Forrás', 'v' => 'CSV import']]);
        $imported++;
    }

    // Tagdíj befizetések importálása után a tagok utolsó fizetés dátumának frissítése
    recalcMembershipPayments($pdo);

    unset($_SESSION['tx_import_preview']);
    $_SESSION['tx_import_results'] = ['imported' => $imported, 'errors' => $preview['errors']];

    if ($imported > 0) {
        flash('success', $imported . ' tranzakció sikeresen importálva' . (!empty($preview['errors']) ? ', ' . count($preview['errors']) . ' sor kihagyva' : '') . '.');
    } else {
        flash('error', 'Egyetlen tranzakció sem lett importálva.');
    }
    header('Location: ' . $redirectBack);
    exit;
}

// ── VALIDATE: CSV beolvasás és ellenőrzés, beszúrás nélkül ────────────────────
if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    flash('error', 'Nem sikerült a fájl feltöltése.');
    header('Location: ' . $redirectBack);
    exit;
}

$mime = mime_content_type($_FILES['csv_file']['tmp_name']);
$allowedMimes = ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'];
if (!in_array($mime, $allowedMimes, true) && !str_ends_with($_FILES['csv_file']['name'], '.csv')) {
    flash('error', 'Csak CSV fájl tölthető fel.');
    header('Location: ' . $redirectBack);
    exit;
}

$handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
if (!$handle) {
    flash('error', 'Nem sikerült a fájl megnyitása.');
    header('Location: ' . $redirectBack);
    exit;
}

$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$header = fgetcsv($handle, 0, ';');
if (!$header) {
    fclose($handle);
    flash('error', 'A CSV fájl üres vagy érvénytelen.');
    header('Location: ' . $redirectBack);
    exit;
}

$validRows     = [];
$errors        = [];
$rowNum        = 1;
$totalDataRows = 0;

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $rowNum++;
    $row = array_pad($row, 9, '');
    $row = array_map('trim', $row);

    [$dateRaw, $typeRaw, $category, $description, $eventRaw, $partner, $amountRaw, $account, $invoiceNo] = $row;

    // Teljesen üres sor → kihagyás
    if (implode('', [$dateRaw, $typeRaw, $category, $description, $partner, $amountRaw, $account]) === '') {
        continue;
    }
    $totalDataRows++;
    $label = ($description !== '' ? $description : ($dateRaw ?: $rowNum . '. sor'));

    // Dátum
    $ts = strtotime($dateRaw);
    if ($dateRaw === '' || $ts === false) {
        $errors[] = ['row' => $rowNum, 'msg' => $label . ': hiányzó vagy érvénytelen dátum (formátum: ÉÉÉÉ-HH-NN).'];
        continue;
    }
    $txDate = date('Y-m-d', $ts);

    // Típus
    $txType = tx_parse_type($typeRaw);
    if ($txType === null) {
        $errors[] = ['row' => $rowNum, 'msg' => $label . ': érvénytelen típus "' . $typeRaw . '" (elfogadott: bevetel / kiadas).'];
        continue;
    }

    // Kötelező szöveges mezők
    if ($category === '')    { $errors[] = ['row' => $rowNum, 'msg' => $label . ': hiányzó kategória.']; continue; }
    if ($description === '') { $errors[] = ['row' => $rowNum, 'msg' => ($dateRaw ?: $rowNum . '. sor') . ': hiányzó leírás.']; continue; }
    if ($partner === '')     { $errors[] = ['row' => $rowNum, 'msg' => $label . ': hiányzó partner.']; continue; }
    if ($account === '')     { $errors[] = ['row' => $rowNum, 'msg' => $label . ': hiányzó számla.']; continue; }

    // Összeg
    $amount = tx_parse_amount($amountRaw);
    if ($amount === null || $amount < 0) {
        $errors[] = ['row' => $rowNum, 'msg' => $label . ': érvénytelen összeg "' . $amountRaw . '".'];
        continue;
    }

    $validRows[] = [
        '_row'           => $rowNum,
        'tx_date'        => $txDate,
        'tx_type'        => $txType,
        'category'       => $category,
        'description'    => $description,
        'event_label'    => $eventRaw !== '' ? $eventRaw : null,
        'partner'        => $partner,
        'amount'         => $amount,
        'account'        => $account,
        'invoice_number' => $invoiceNo !== '' ? $invoiceNo : null,
    ];
}

fclose($handle);

$_SESSION['tx_import_preview'] = [
    'rows'            => $validRows,
    'errors'          => $errors,
    'total_data_rows' => $totalDataRows,
];

header('Location: ' . $redirectBack);
exit;
