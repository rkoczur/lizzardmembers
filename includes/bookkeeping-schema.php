<?php
/**
 * Pénzügyi „könyvelés" séma — tranzakciós napló + előre definiált értékek.
 * A meglévő séma-fájlok mintájára (audit-schema.php, future-tours-schema.php).
 */
function ensureBookkeepingSchema(PDO $pdo): void
{
    // Tranzakciós napló
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transactions` (
        `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
        `tx_date`        DATE             NOT NULL,
        `tx_type`        ENUM('income','expense') NOT NULL,
        `category`       VARCHAR(255)     NOT NULL,
        `description`    TEXT             NOT NULL,
        `event_type`     ENUM('tour','future_tour') DEFAULT NULL,
        `event_id`       INT UNSIGNED     DEFAULT NULL,
        `event_label`    VARCHAR(255)     DEFAULT NULL,
        `partner`        VARCHAR(255)     NOT NULL,
        `amount`         DECIMAL(12,2)    NOT NULL,
        `account`        VARCHAR(255)     NOT NULL,
        `invoice_number` VARCHAR(100)     DEFAULT NULL,
        `created_by`     INT UNSIGNED     DEFAULT NULL,
        `created_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at`     TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_tx_date` (`tx_date`),
        KEY `idx_tx_type` (`tx_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Előre definiált értékek: kategória, partner, számla
    $pdo->exec("CREATE TABLE IF NOT EXISTS `transaction_presets` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `preset_type` ENUM('category','partner','account') NOT NULL,
        `value`       VARCHAR(255) NOT NULL,
        `sort_order`  INT          NOT NULL DEFAULT 0,
        `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_type_value` (`preset_type`, `value`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Kiemelés (folyamatban lévő tétel) — migráció meglévő telepítéshez
    try { $pdo->exec("ALTER TABLE `transactions` ADD COLUMN `highlighted` TINYINT(1) NOT NULL DEFAULT 0"); } catch (Throwable) {}
}

/**
 * Egy előre definiált típus értékeinek lekérése (sorrend szerint).
 * @return string[]
 */
function getTransactionPresets(PDO $pdo, string $type): array
{
    $stmt = $pdo->prepare("SELECT value FROM transaction_presets WHERE preset_type = ? ORDER BY sort_order ASC, value ASC");
    $stmt->execute([$type]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Az űrlap „event" mezőjének (`tour:ID` / `future_tour:ID` / üres) feloldása.
 * @return array{type: ?string, id: ?int, label: ?string}
 */
function resolveTransactionEvent(PDO $pdo, string $raw): array
{
    $none = ['type' => null, 'id' => null, 'label' => null];
    if ($raw === '' || !str_contains($raw, ':')) return $none;

    [$type, $idStr] = explode(':', $raw, 2);
    $id = (int)$idStr;
    if ($id <= 0) return $none;

    if ($type === 'tour') {
        $stmt = $pdo->prepare("SELECT COALESCE(NULLIF(name,''), CONCAT(country, ' túra')) AS label, tour_date FROM tours WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return $none;
        $label = $row['label'];
        if (!empty($row['tour_date'])) $label .= ' (' . (new DateTime($row['tour_date']))->format('Y.m.d') . ')';
        return ['type' => 'tour', 'id' => $id, 'label' => $label];
    }

    if ($type === 'future_tour') {
        $stmt = $pdo->prepare("SELECT name, start_date FROM future_tours WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) return $none;
        $label = $row['name'];
        if (!empty($row['start_date'])) $label .= ' (' . (new DateTime($row['start_date']))->format('Y.m.d') . ')';
        return ['type' => 'future_tour', 'id' => $id, 'label' => $label];
    }

    return $none;
}

/**
 * Esemény-címke (event_label) feloldása konkrét túrára a NÉV alapján.
 * Az importált tételeknél csak a túra neve van eltárolva (event_id nélkül) — ez összeköti őket
 * a tényleges túra-rekorddal, ha a név egyértelműen egyezik.
 * @return array{type: ?string, id: ?int}
 */
function resolveEventByLabel(PDO $pdo, string $label): array
{
    $none = ['type' => null, 'id' => null];
    $label = trim($label);
    if ($label === '') return $none;

    $s = $pdo->prepare("SELECT id FROM tours WHERE name = ? LIMIT 2");
    $s->execute([$label]);
    $ids = $s->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) === 1) return ['type' => 'tour', 'id' => (int)$ids[0]];

    $s = $pdo->prepare("SELECT id FROM future_tours WHERE name = ? LIMIT 2");
    $s->execute([$label]);
    $ids = $s->fetchAll(PDO::FETCH_COLUMN);
    if (count($ids) === 1) return ['type' => 'future_tour', 'id' => (int)$ids[0]];

    return $none; // nincs egyértelmű egyezés (0 vagy több találat)
}

/** Audit-naplóhoz olvasható címke egy tranzakcióhoz. */
function transactionAuditLabel(string $date, string $type, string $category, $amount): string
{
    $typeLabel = $type === 'income' ? 'Bevétel' : 'Kiadás';
    $dateLabel = $date !== '' ? (new DateTime($date))->format('Y.m.d') : '—';
    return $dateLabel . ' – ' . $typeLabel . ' – ' . $category . ' – ' . number_format((float)$amount, 0, ',', ' ') . ' Ft';
}
