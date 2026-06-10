<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdminOrVezeto();
if (!isRootAdmin()) { header('Location: ' . BASE_URL . '/admin/index.php'); exit; }

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$zipAvailable = class_exists('ZipArchive');

$backupDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
$backups   = [];
if (is_dir($backupDir)) {
    foreach (glob($backupDir . DIRECTORY_SEPARATOR . 'backup_*.zip') as $f) {
        $backups[] = ['name' => basename($f), 'size' => (int)filesize($f), 'mtime' => (int)filemtime($f)];
    }
    usort($backups, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
}

$fmtSize = function (int $bytes): string {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576)    return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)       return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
};

$pageTitle  = 'Mentés';
$activePage = 'backup';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/security.php" class="tab-link<?= $activePage === 'security' ? ' active' : '' ?>">Biztonság</a>
  <a href="<?= BASE_URL ?>/admin/logs.php" class="tab-link<?= $activePage === 'logs' ? ' active' : '' ?>">Naplók</a>
  <a href="<?= BASE_URL ?>/admin/settings.php" class="tab-link<?= $activePage === 'settings' ? ' active' : '' ?>">Beállítások</a>
  <a href="<?= BASE_URL ?>/admin/orphaned-assets.php" class="tab-link<?= $activePage === 'tools' ? ' active' : '' ?>">Felesleges fájlok</a>
  <a href="<?= BASE_URL ?>/admin/backup.php" class="tab-link<?= $activePage === 'backup' ? ' active' : '' ?>">Mentés</a>
</div>

<div class="page-header">
  <h1>Mentés (teljes oldal + adatbázis)</h1>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

  <!-- Új mentés -->
  <div class="card">
    <div class="card-header"><h2>Új mentés készítése</h2></div>
    <div class="card-body">
      <p style="color:var(--text-muted);font-size:13px;line-height:1.6;margin-bottom:16px;">
        A mentés egyetlen ZIP fájlba csomagolja a <strong>teljes weboldalt</strong> (összes fájl) és a <strong>teljes adatbázist</strong> (<code>database.sql</code>).
        Nagy oldalnál a folyamat eltarthat egy ideig — kérlek várj, amíg befejeződik.
      </p>
      <?php if ($zipAvailable): ?>
        <form method="post" action="<?= BASE_URL ?>/actions/backup-create.php"
              onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Mentés készítése…';">
          <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
          <button type="submit" class="btn btn-primary">💾 Teljes mentés készítése</button>
        </form>
      <?php else: ?>
        <div class="alert alert-error" style="margin:0;">
          A <code>ZipArchive</code> PHP-bővítmény nem érhető el ezen a szerveren, ezért a beépített mentés nem használható.
          Készíts manuális mentést: adatbázis export phpMyAdmin-ban + fájlok letöltése FTP-n.
        </div>
      <?php endif; ?>

      <div style="margin-top:18px;padding:14px 16px;background:var(--danger-bg,#fff1f0);border:1px solid var(--border);border-radius:8px;font-size:12.5px;color:var(--text);line-height:1.6;">
        ⚠ <strong>Biztonsági figyelmeztetés:</strong> a mentés érzékeny adatokat tartalmaz (a <code>config.ini</code>-ben az adatbázis-jelszó, valamint a tagok személyes adatai). Tárold biztonságos helyen, és töröld a régi mentéseket, ha már nincs rájuk szükség.
      </div>
    </div>
  </div>

  <!-- Visszaállítás (kézi) -->
  <div class="card">
    <div class="card-header"><h2>Visszaállítás (kézi)</h2></div>
    <div class="card-body" style="font-size:13px;color:var(--text);line-height:1.65;">
      <p style="margin-bottom:10px;color:var(--text-muted);">A visszaállítás manuálisan történik a letöltött ZIP-ből (a részletes lépések a ZIP-ben lévő <code>RESTORE.txt</code>-ben is megtalálhatók):</p>
      <ol style="margin:0 0 0 18px;padding:0;">
        <li style="margin-bottom:6px;">Csomagold ki a ZIP-et. A <code>site/</code> mappa a weboldal tartalma, a <code>database.sql</code> az adatbázis.</li>
        <li style="margin-bottom:6px;">Töltsd fel a <code>site/</code> tartalmát FTP-n a webgyökérbe (a <code>config.ini</code>-t a célkörnyezethez igazítsd).</li>
        <li style="margin-bottom:6px;">Importáld a <code>database.sql</code>-t phpMyAdmin-ban (Import), vagy: <code>mysql -u user -p dbnev &lt; database.sql</code></li>
        <li>Jelentkezz be és ellenőrizd az adatokat.</li>
      </ol>
    </div>
  </div>

</div>

<!-- Meglévő mentések -->
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h2>Elérhető mentések<?= $backups ? ' (' . count($backups) . ')' : '' ?></h2></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Fájl</th>
          <th style="width:170px;">Készült</th>
          <th style="width:110px;">Méret</th>
          <th style="width:200px;"></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($backups)): ?>
          <tr><td colspan="4"><div class="empty-state"><p>Még nincs mentés. Készíts egyet a fenti gombbal.</p></div></td></tr>
        <?php else: foreach ($backups as $b): ?>
          <tr>
            <td style="font-family:monospace;font-size:12.5px;"><?= e($b['name']) ?></td>
            <td><?= e(date('Y.m.d H:i', $b['mtime'])) ?></td>
            <td><?= $fmtSize($b['size']) ?></td>
            <td>
              <div style="display:flex;gap:6px;justify-content:flex-end;">
                <form method="post" action="<?= BASE_URL ?>/actions/backup-download.php" style="margin:0;">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="name" value="<?= e($b['name']) ?>">
                  <button type="submit" class="btn btn-ghost btn-sm">Letöltés</button>
                </form>
                <form method="post" action="<?= BASE_URL ?>/actions/backup-delete.php" style="margin:0;"
                      onsubmit="return confirm('Biztosan törlöd ezt a mentést?');">
                  <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                  <input type="hidden" name="name" value="<?= e($b['name']) ?>">
                  <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
