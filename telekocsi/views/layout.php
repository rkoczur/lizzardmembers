<?php
/** Oldalkeret. Vár: $view ('landing'|'board'), $board, $flash. */
$title = $board ? (($board['title'] ?: 'Telekocsi') . ' · ' . tkFormatCode($board['code'])) : 'Telekocsi-szervező';
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=Space+Grotesk:wght@400;500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= e(tkBase()) ?>/assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
</head>
<body class="tk-<?= e($view) ?>">
  <div class="tk-lane" aria-hidden="true"></div>
  <main class="tk-wrap">
    <?php if ($flash): ?>
      <div class="tk-flash tk-flash-<?= e($flash['type']) ?>" data-tk-flash><?= e($flash['msg']) ?></div>
    <?php endif; ?>
    <?php include __DIR__ . '/' . $view . '.php'; ?>
  </main>
  <footer class="tk-foot">
    <a href="<?= e(tkBase()) ?>/">Telekocsi-szervező</a> · ingyenes, regisztráció nélkül
  </footer>
  <script src="<?= e(tkBase()) ?>/assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>" defer></script>
</body>
</html>
