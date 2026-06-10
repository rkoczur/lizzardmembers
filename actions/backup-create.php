<?php
/**
 * Teljes oldal mentés készítése: az összes fájl + a teljes adatbázis egy ZIP-ben.
 * Tiszta PHP (PDO dump + ZipArchive) — nem igényel mysqldump/exec-et.
 * Csak a fő admin (Egyesületvezető) használhatja. A mentés titkokat és PII-t tartalmaz.
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-schema.php';
requireAdminOrVezeto();
if (!isRootAdmin()) { header('Location: ' . BASE_URL . '/user/index.php'); exit; }
verifyCsrf();

$backupPage = BASE_URL . '/admin/backup.php';

if (!class_exists('ZipArchive')) {
    flash('error', 'A ZipArchive PHP-bővítmény nem érhető el a szerveren, ezért a beépített mentés nem használható. Kérj manuális mentést (phpMyAdmin + FTP).');
    header('Location: ' . $backupPage);
    exit;
}

@set_time_limit(0);
@ignore_user_abort(true);

$appRoot   = realpath(dirname(__DIR__));
$backupDir = $appRoot . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}
// .htaccess biztosítása (közvetlen web-hozzáférés tiltása)
$htaccess = $backupDir . DIRECTORY_SEPARATOR . '.htaccess';
if (!file_exists($htaccess)) {
    @file_put_contents($htaccess, "Options -Indexes\nOrder Allow,Deny\nDeny from all\nRequire all denied\n");
}
if (!is_writable($backupDir)) {
    flash('error', 'A backups/ mappa nem írható a szerveren. Ellenőrizd a jogosultságokat.');
    header('Location: ' . $backupPage);
    exit;
}

$pdo = getDb();
ensureAuditSchema($pdo);

// ── 1) Adatbázis dump ideiglenes fájlba ──────────────────────────────────────
$sqlTmp = $backupDir . DIRECTORY_SEPARATOR . 'database.sql.tmp';
$tables = [];
try {
    $fh = fopen($sqlTmp, 'w');
    fwrite($fh, "-- " . APP_NAME . " adatbázis mentés\n-- Készült: " . date('Y-m-d H:i:s') . "\n-- Adatbázis: " . DB_NAME . "\n\n");
    fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $tbl = str_replace('`', '``', $table);

        fwrite($fh, "-- ----- Tábla: $table -----\n");
        fwrite($fh, "DROP TABLE IF EXISTS `$tbl`;\n");

        $createRow  = $pdo->query("SHOW CREATE TABLE `$tbl`")->fetch(PDO::FETCH_ASSOC);
        $createSql  = $createRow['Create Table'] ?? ($createRow['Create View'] ?? '');
        fwrite($fh, $createSql . ";\n\n");

        // Adatsorok kötegelve
        $stmt = $pdo->query("SELECT * FROM `$tbl`");
        $buffer = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = ($v === null) ? 'NULL' : $pdo->quote((string)$v);
            }
            $buffer[] = '(' . implode(',', $vals) . ')';
            if (count($buffer) >= 200) {
                fwrite($fh, "INSERT INTO `$tbl` VALUES\n" . implode(",\n", $buffer) . ";\n");
                $buffer = [];
            }
        }
        if ($buffer) {
            fwrite($fh, "INSERT INTO `$tbl` VALUES\n" . implode(",\n", $buffer) . ";\n");
        }
        fwrite($fh, "\n");
    }

    fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fh);
} catch (Throwable $e) {
    if (!empty($fh) && is_resource($fh)) fclose($fh);
    @unlink($sqlTmp);
    error_log('Backup DB dump error: ' . $e->getMessage());
    flash('error', 'Az adatbázis mentése sikertelen: ' . $e->getMessage());
    header('Location: ' . $backupPage);
    exit;
}

// ── 2) ZIP összeállítása (fájlok + database.sql + manifest + RESTORE.txt) ────
$zipName = 'backup_' . date('Y-m-d_His') . '.zip';
$zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipName;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($sqlTmp);
    flash('error', 'Nem sikerült létrehozni a ZIP archívumot.');
    header('Location: ' . $backupPage);
    exit;
}

// Kihagyandó mappák (valós útvonal-prefix szerint): maga a backups/, a VCS és a helyi metaadatok
$skip = array_filter([
    realpath($backupDir),
    realpath($appRoot . DIRECTORY_SEPARATOR . '.git'),
    realpath($appRoot . DIRECTORY_SEPARATOR . '.claude'),
]);

$fileCount = 0;
$iterator = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($appRoot, FilesystemIterator::SKIP_DOTS),
        function ($current) use ($skip) {
            $path = $current->getPathname();
            foreach ($skip as $s) {
                if ($path === $s || strpos($path, $s . DIRECTORY_SEPARATOR) === 0) return false;
            }
            return true;
        }
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
);
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $abs = $file->getPathname();
    $rel = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', substr($abs, strlen($appRoot))), '/');
    $zip->addFile($abs, 'site/' . $rel);
    $fileCount++;
}

// Adatbázis dump
$zip->addFile($sqlTmp, 'database.sql');

// Manifest
$manifest = [
    'app'          => APP_NAME,
    'app_version'  => defined('APP_VERSION') ? APP_VERSION : '',
    'created_at'   => date('Y-m-d H:i:s'),
    'db_name'      => DB_NAME,
    'table_count'  => count($tables),
    'tables'       => $tables,
    'file_count'   => $fileCount,
    'php_version'  => PHP_VERSION,
    'note'         => 'Teljes oldal mentés (fájlok + adatbázis). Visszaállítás: lásd RESTORE.txt',
];
$zip->addFromString('backup-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

// Visszaállítási útmutató
$restore = "VISSZAÁLLÍTÁSI ÚTMUTATÓ — " . APP_NAME . "\n"
    . "Készült: " . date('Y-m-d H:i:s') . "\n"
    . str_repeat('=', 60) . "\n\n"
    . "A 'site/' mappa a weboldal teljes tartalma, a 'database.sql' a teljes adatbázis.\n\n"
    . "1. FÁJLOK\n"
    . "   - Töltsd fel a 'site/' mappa TARTALMÁT a webgyökérbe (FTP).\n"
    . "   - A config.ini-t a célkörnyezethez igazítsd (DB-adatok, base_url), ha eltér.\n\n"
    . "2. ADATBÁZIS\n"
    . "   - Hozz létre (vagy ürítsd ki) a cél adatbázist.\n"
    . "   - Importáld a database.sql-t phpMyAdmin-ban (Import fül),\n"
    . "     vagy parancssorból: mysql -u USER -p DBNEV < database.sql\n"
    . "   - A dump tartalmazza a DROP TABLE + CREATE TABLE utasításokat, így a meglévő táblákat felülírja.\n\n"
    . "3. ELLENŐRZÉS\n"
    . "   - Jelentkezz be adminként és nézd át az adatokat.\n\n"
    . "FONTOS: ez az archívum érzékeny adatokat tartalmaz (adatbázis-jelszó a config.ini-ben, tagok személyes adatai). Tárold biztonságosan, és töröld, ha már nincs rá szükség.\n";
$zip->addFromString('RESTORE.txt', $restore);

$zip->close();
@unlink($sqlTmp);

if (!file_exists($zipPath)) {
    flash('error', 'A mentés létrehozása nem sikerült.');
    header('Location: ' . $backupPage);
    exit;
}

$sizeMb = round(filesize($zipPath) / 1048576, 1);
logAudit($pdo, 'create', 'backup', 0, 'Teljes mentés: ' . $zipName, [
    ['k' => 'Fájlok', 'f' => '', 't' => (string)$fileCount],
    ['k' => 'Táblák', 'f' => '', 't' => (string)count($tables)],
    ['k' => 'Méret',  'f' => '', 't' => $sizeMb . ' MB'],
]);

flash('success', 'Mentés elkészült: ' . $zipName . ' (' . $sizeMb . ' MB, ' . $fileCount . ' fájl, ' . count($tables) . ' tábla).');
header('Location: ' . $backupPage);
exit;
