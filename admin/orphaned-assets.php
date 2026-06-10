<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDb();

// ── Delete handler ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $projectRoot = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR;
    $uploadsRoot = realpath($projectRoot . 'assets' . DIRECTORY_SEPARATOR . 'uploads') . DIRECTORY_SEPARATOR;

    $allowedDirs = [
        'img'         => realpath($projectRoot . 'assets' . DIRECTORY_SEPARATOR . 'img') . DIRECTORY_SEPARATOR,
        'avatars'     => $uploadsRoot . 'avatars'     . DIRECTORY_SEPARATOR,
        'docs'        => $uploadsRoot . 'docs'        . DIRECTORY_SEPARATOR,
        'flags'       => $uploadsRoot . 'flags'       . DIRECTORY_SEPARATOR,
        'gpx'         => $uploadsRoot . 'gpx'         . DIRECTORY_SEPARATOR,
        'hero'        => $uploadsRoot . 'hero'        . DIRECTORY_SEPARATOR,
        'posts'       => $uploadsRoot . 'posts'       . DIRECTORY_SEPARATOR,
        'tour-covers' => $uploadsRoot . 'tour-covers' . DIRECTORY_SEPARATOR,
    ];

    $dirKey    = $_POST['dir_key'] ?? '';
    $filenames = isset($_POST['filenames']) ? (array)$_POST['filenames'] : [];

    $deleted = $errors = 0;

    if (array_key_exists($dirKey, $allowedDirs) && !empty($filenames)) {
        $dirPath = $allowedDirs[$dirKey];
        $realDir = realpath(rtrim($dirPath, '\\/'));

        foreach ($filenames as $fname) {
            $fname = trim((string)$fname);
            // Security: only plain basenames, no path separators or dot-files
            if ($fname === '' || $fname[0] === '.' || strpbrk($fname, '/\\:') !== false || basename($fname) !== $fname) {
                $errors++;
                continue;
            }
            $fullPath = $dirPath . $fname;
            if (!is_file($fullPath)) {
                $errors++;
                continue;
            }
            // Extra: realpath must sit directly inside the allowed directory (catches symlinks)
            $realFull = realpath($fullPath);
            if ($realFull === false || $realDir === false ||
                strncmp($realFull, $realDir . DIRECTORY_SEPARATOR, strlen($realDir) + 1) !== 0) {
                $errors++;
                continue;
            }
            if (@unlink($fullPath)) {
                $deleted++;
            } else {
                $errors++;
            }
        }
    }

    $msg = $deleted . ' fájl törölve' . ($errors > 0 ? ', ' . $errors . ' törlés sikertelen' : '') . '.';
    flash('success', $msg);
    header('Location: ' . BASE_URL . '/admin/orphaned-assets.php?scan=1');
    exit;
}

// ── Scan ──────────────────────────────────────────────────────────────────────
$doScan  = isset($_GET['scan']);
$results = [];
$totalFiles = $totalOrphans = $totalOrphanBytes = 0;
$scanError = '';

if ($doScan) {
    set_time_limit(120);

    $projectRoot = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR;
    $uploadsRoot = realpath($projectRoot . 'assets' . DIRECTORY_SEPARATOR . 'uploads') . DIRECTORY_SEPARATOR;

    $dirsToScan = [
        ['key' => 'img',         'label' => 'assets/img',          'path' => realpath($projectRoot . 'assets' . DIRECTORY_SEPARATOR . 'img') . DIRECTORY_SEPARATOR],
        ['key' => 'avatars',     'label' => 'uploads/avatars',     'path' => $uploadsRoot . 'avatars'     . DIRECTORY_SEPARATOR],
        ['key' => 'docs',        'label' => 'uploads/docs',        'path' => $uploadsRoot . 'docs'        . DIRECTORY_SEPARATOR],
        ['key' => 'flags',       'label' => 'uploads/flags',       'path' => $uploadsRoot . 'flags'       . DIRECTORY_SEPARATOR],
        ['key' => 'gpx',         'label' => 'uploads/gpx',         'path' => $uploadsRoot . 'gpx'         . DIRECTORY_SEPARATOR],
        ['key' => 'hero',        'label' => 'uploads/hero',        'path' => $uploadsRoot . 'hero'        . DIRECTORY_SEPARATOR],
        ['key' => 'posts',       'label' => 'uploads/posts',       'path' => $uploadsRoot . 'posts'       . DIRECTORY_SEPARATOR],
        ['key' => 'tour-covers', 'label' => 'uploads/tour-covers', 'path' => $uploadsRoot . 'tour-covers' . DIRECTORY_SEPARATOR],
    ];

    // Source content: all PHP/CSS/JS files, excluding uploads dir
    $sourceContent = '';
    try {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['php', 'css', 'js'], true)) continue;
            $rp = $file->getRealPath();
            if (strncmp($rp, $uploadsRoot, strlen($uploadsRoot)) === 0) continue;
            $c = @file_get_contents($rp);
            if ($c !== false) $sourceContent .= $c . "\n";
        }
    } catch (Throwable $ex) {
        $scanError = 'Forrásfájlok olvasása sikertelen: ' . $ex->getMessage();
    }

    // DB filenames
    $dbContent = '';
    $dbQueries = [
        "SELECT avatar         FROM users            WHERE avatar         IS NOT NULL AND avatar         != ''",
        "SELECT gpx_file       FROM tours            WHERE gpx_file       IS NOT NULL AND gpx_file       != ''",
        "SELECT filename       FROM tour_gpx_files",
        "SELECT filename       FROM future_tour_gpx_files",
        "SELECT cover_img      FROM future_tours     WHERE cover_img      IS NOT NULL AND cover_img      != ''",
        "SELECT cover_img      FROM posts            WHERE cover_img      IS NOT NULL AND cover_img      != ''",
        "SELECT filename       FROM documents        WHERE filename       IS NOT NULL AND filename       != ''",
        "SELECT flag_filename  FROM countries        WHERE flag_filename  IS NOT NULL AND flag_filename  != ''",
        "SELECT body           FROM pages            WHERE slug = 'hero-image' AND body IS NOT NULL AND body != ''",
    ];
    foreach ($dbQueries as $sql) {
        try {
            $stmt = $pdo->query($sql);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $val) {
                $dbContent .= $val . "\n";
            }
        } catch (Throwable) {}
    }

    $combined = $sourceContent . $dbContent;

    foreach ($dirsToScan as $dir) {
        $path = $dir['path'];
        if (!is_dir($path)) continue;

        $entries = @scandir($path);
        if ($entries === false) continue;

        $orphans = [];
        $count   = 0;
        foreach ($entries as $fname) {
            if ($fname[0] === '.') continue;
            $fp = $path . $fname;
            if (!is_file($fp)) continue;
            $count++;
            $totalFiles++;
            if (strpos($combined, $fname) === false) {
                $size = (int)filesize($fp);
                $orphans[] = ['name' => $fname, 'size' => $size, 'path' => $fp];
                $totalOrphans++;
                $totalOrphanBytes += $size;
            }
        }

        usort($orphans, fn($a, $b) => strcmp($a['name'], $b['name']));

        $results[] = [
            'key'     => $dir['key'],
            'label'   => $dir['label'],
            'count'   => $count,
            'orphans' => $orphans,
        ];
    }
}

function _fmtBytes(int $b): string
{
    if ($b >= 1_048_576) return number_format($b / 1_048_576, 1, ',', ' ') . ' MB';
    if ($b >= 1_024)     return number_format($b / 1_024,     1, ',', ' ') . ' KB';
    return $b . ' B';
}

$pageTitle  = 'Felesleges fájlok';
$activePage = 'tools';
include __DIR__ . '/../includes/admin-header.php';
?>

<div class="tab-nav">
  <a href="<?= BASE_URL ?>/admin/security.php" class="tab-link<?= $activePage === 'security' ? ' active' : '' ?>">Biztonság</a>
  <a href="<?= BASE_URL ?>/admin/logs.php" class="tab-link<?= $activePage === 'logs' ? ' active' : '' ?>">Naplók</a>
  <a href="<?= BASE_URL ?>/admin/settings.php" class="tab-link<?= $activePage === 'settings' ? ' active' : '' ?>">Beállítások</a>
  <a href="<?= BASE_URL ?>/admin/orphaned-assets.php" class="tab-link<?= $activePage === 'tools' ? ' active' : '' ?>">Felesleges fájlok</a>
  <?php if (isRootAdmin()): ?><a href="<?= BASE_URL ?>/admin/backup.php" class="tab-link<?= $activePage === 'backup' ? ' active' : '' ?>">Mentés</a><?php endif; ?>
</div>

<div class="page-header">
  <div>
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <div class="page-subtitle">Nem hivatkozott fájlok keresése az assets könyvtárban</div>
  </div>
</div>

<?php if ($msg = getFlash('success')): ?>
  <div class="alert alert-success"><?= e($msg) ?></div>
<?php endif; ?>
<?php if ($scanError): ?>
  <div class="alert alert-danger"><?= e($scanError) ?></div>
<?php endif; ?>

<?php if (!$doScan): ?>

<div class="card">
  <div class="card-body" style="text-align:center;padding:48px 24px;">
    <div style="font-size:48px;margin-bottom:16px;">🔍</div>
    <div style="font-size:17px;font-weight:700;margin-bottom:8px;">Felesleges fájlok keresése</div>
    <div style="color:var(--text-muted);font-size:14px;margin-bottom:24px;max-width:500px;margin-left:auto;margin-right:auto;line-height:1.6;">
      A szkennelés végigolvassa az összes PHP, CSS és JS forrásfájlt és az adatbázis összes fájlhivatkozását, majd összeveti az <code>assets/img/</code> és <code>assets/uploads/</code> mappa tartalmával. A nem hivatkozott fájlok felkerülnek a listára.
    </div>
    <a href="?scan=1" class="btn btn-primary">Szkennelés indítása</a>
  </div>
</div>

<?php else: ?>

<!-- Summary stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;">
  <div class="card">
    <div class="card-body" style="text-align:center;padding:20px 16px;">
      <div style="font-size:30px;font-weight:800;"><?= $totalFiles ?></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Átvizsgált fájl</div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:20px 16px;">
      <div style="font-size:30px;font-weight:800;color:<?= $totalOrphans > 0 ? 'var(--danger)' : 'var(--success)' ?>;"><?= $totalOrphans ?></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Felesleges fájl</div>
    </div>
  </div>
  <div class="card">
    <div class="card-body" style="text-align:center;padding:20px 16px;">
      <div style="font-size:30px;font-weight:800;color:var(--text-muted);"><?= _fmtBytes($totalOrphanBytes) ?></div>
      <div style="font-size:11px;color:var(--text-muted);margin-top:4px;text-transform:uppercase;letter-spacing:.05em;">Felszabadítható</div>
    </div>
  </div>
</div>

<!-- Per-directory results -->
<?php foreach ($results as $dir): ?>
<div class="card" style="margin-bottom:16px;">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
    <h2 style="font-family:monospace;font-size:13.5px;font-weight:600;"><?= e($dir['label']) ?></h2>
    <span style="font-size:12px;color:var(--text-muted);flex-shrink:0;"><?= $dir['count'] ?> fájl</span>
  </div>

  <?php if (empty($dir['orphans'])): ?>
    <div class="card-body" style="display:flex;align-items:center;gap:8px;color:var(--success);font-size:13.5px;">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px;flex-shrink:0;"><polyline points="20 6 9 17 4 12"/></svg>
      Nincs felesleges fájl
    </div>

  <?php else: ?>

    <!-- Delete-all bar -->
    <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;background:var(--danger-bg,#fff5f5);">
      <span style="font-size:12.5px;color:var(--danger);font-weight:600;">
        <?= count($dir['orphans']) ?> felesleges fájl — <?= _fmtBytes(array_sum(array_column($dir['orphans'], 'size'))) ?>
      </span>
      <form method="post" action="<?= BASE_URL ?>/admin/orphaned-assets.php?scan=1"
            onsubmit="return confirm('Biztosan törlöd az összes felesleges fájlt a(z) <?= e(addslashes($dir['label'])) ?> mappából? Ez nem visszavonható!');">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <input type="hidden" name="dir_key" value="<?= e($dir['key']) ?>">
        <?php foreach ($dir['orphans'] as $f): ?>
          <input type="hidden" name="filenames[]" value="<?= e($f['name']) ?>">
        <?php endforeach; ?>
        <button type="submit" class="btn btn-danger btn-sm">Összes törlése</button>
      </form>
    </div>

    <!-- Files table -->
    <div style="overflow-x:auto;">
      <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
          <tr style="background:var(--card);border-bottom:1px solid var(--border);">
            <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Fájlnév</th>
            <th style="padding:8px 16px;text-align:right;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;white-space:nowrap;">Méret</th>
            <th style="padding:8px 16px;text-align:left;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;">Elérési út</th>
            <th style="padding:8px 16px;text-align:right;color:var(--text-muted);font-size:11px;text-transform:uppercase;letter-spacing:.04em;font-weight:600;white-space:nowrap;"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dir['orphans'] as $f): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:9px 16px;font-weight:600;color:var(--danger);"><?= e($f['name']) ?></td>
            <td style="padding:9px 16px;text-align:right;color:var(--text-muted);white-space:nowrap;"><?= _fmtBytes($f['size']) ?></td>
            <td style="padding:9px 16px;color:var(--text-muted);font-size:11.5px;word-break:break-all;"><?= e($f['path']) ?></td>
            <td style="padding:6px 16px;text-align:right;white-space:nowrap;">
              <form method="post" action="<?= BASE_URL ?>/admin/orphaned-assets.php?scan=1"
                    onsubmit="return confirm('Biztosan törlöd: <?= e(addslashes($f['name'])) ?>?');">
                <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                <input type="hidden" name="dir_key" value="<?= e($dir['key']) ?>">
                <input type="hidden" name="filenames[]" value="<?= e($f['name']) ?>">
                <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php endif; ?>
</div>
<?php endforeach; ?>

<div style="margin-top:8px;text-align:right;">
  <a href="<?= BASE_URL ?>/admin/orphaned-assets.php" class="btn btn-secondary btn-sm">↺ Újabb szkennelés</a>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
