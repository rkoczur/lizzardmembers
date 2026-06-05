<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$pageTitle     = 'Toplista – Lizzardier pontverseny';
$activePubPage = 'toplista';
include __DIR__ . '/../includes/public-header.php';
?>

<div class="pub-wrap">
  <div class="pub-page-header">
    <h1>Toplista</h1>
    <p>A Lizzardier pontverseny örökös és éves eredményei.</p>
  </div>

  <?php include __DIR__ . '/../includes/toplist-content.php'; ?>
</div>

<?php include __DIR__ . '/../includes/public-footer.php'; ?>
