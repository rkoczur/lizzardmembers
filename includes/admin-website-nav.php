<?php
$_websitePages = [
    'posts'     => [BASE_URL . '/admin/posts.php',     'Posztok'],
    'documents' => [BASE_URL . '/admin/documents.php', 'Dokumentumtár'],
    'faq'       => [BASE_URL . '/admin/faq.php',       'GYIK'],
    'pages'     => [BASE_URL . '/admin/pages.php',     'Lapok'],
];
$_currentFile = basename($_SERVER['PHP_SELF'], '.php');
$_activeTab   = match($_currentFile) {
    'posts','post-detail' => 'posts',
    'documents'           => 'documents',
    'faq'                 => 'faq',
    'pages'               => 'pages',
    default               => '',
};
?>
<div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap;">
  <?php foreach ($_websitePages as $key => [$url, $label]): ?>
    <a href="<?= $url ?>" class="btn btn-sm <?= $_activeTab === $key ? 'btn-primary' : 'btn-secondary' ?>"><?= $label ?></a>
  <?php endforeach; ?>
  <a href="<?= BASE_URL ?>/public/index.php" target="_blank" class="btn btn-sm btn-ghost" style="margin-left:auto;">Nyilvános oldal →</a>
</div>
