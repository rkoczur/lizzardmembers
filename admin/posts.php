<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLeader();
$ro = !canManagePosts();

$pdo = getDb();
ensurePublicSchema($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$filter = in_array($_GET['cat'] ?? '', ['hirek','beszmolok']) ? $_GET['cat'] : '';
$sql = "SELECT p.*, u.firstname, u.lastname FROM posts p LEFT JOIN users u ON u.id = p.created_by";
if ($filter) $sql .= " WHERE p.category = " . $pdo->quote($filter);
$sql .= " ORDER BY p.created_at DESC";
$posts = $pdo->query($sql)->fetchAll();

$pageTitle  = 'Weboldal – Posztok';
$activePage = 'website';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/admin-website-nav.php'; ?>

<div class="page-header">
  <h1>Posztok</h1>
  <?php if (!$ro): ?><a href="<?= BASE_URL ?>/admin/post-detail.php?new=1" class="btn btn-primary">+ Új poszt</a><?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <div style="display:flex;gap:8px;">
      <a href="?" class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-ghost' ?>">Összes</a>
      <a href="?cat=hirek" class="btn btn-sm <?= $filter === 'hirek' ? 'btn-primary' : 'btn-ghost' ?>">Hírek</a>
      <a href="?cat=beszmolok" class="btn btn-sm <?= $filter === 'beszmolok' ? 'btn-primary' : 'btn-ghost' ?>">Élményblog</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Cím</th>
          <th>Kategória</th>
          <th>Státusz</th>
          <th>Szerző</th>
          <th>Létrehozva</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($posts as $p): ?>
        <tr>
          <td>
            <div style="font-weight:600;"><?= e($p['title']) ?></div>
            <div style="font-size:11px;color:var(--text-muted);">/<?= e($p['slug']) ?></div>
          </td>
          <td>
            <span class="pub-post-category-badge <?= e($p['category']) ?>">
              <?= $p['category'] === 'hirek' ? 'Hírek' : 'Élményblog' ?>
            </span>
          </td>
          <td>
            <?php if ($p['published']): ?>
              <span class="badge badge-active">Publikált</span>
            <?php else: ?>
              <span class="badge badge-inactive">Vázlat</span>
            <?php endif; ?>
          </td>
          <td><?= e(trim(($p['lastname'] ?? '') . ' ' . ($p['firstname'] ?? ''))) ?: '—' ?></td>
          <td style="white-space:nowrap;"><?= formatDate($p['created_at']) ?></td>
          <td class="td-actions" style="white-space:nowrap;">
            <?php if (!$ro): ?>
              <a href="<?= BASE_URL ?>/admin/post-detail.php?id=<?= (int)$p['id'] ?>" class="btn btn-ghost btn-sm">Szerkesztés</a>
            <?php endif; ?>
            <?php if ($p['published']): ?>
              <a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">Megtekintés</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($posts)): ?>
        <tr><td colspan="6" class="empty-state"><p>Nincsenek posztok.</p></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
