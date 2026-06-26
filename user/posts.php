<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireUser();
if (!canCreatePosts()) { header('Location: ' . BASE_URL . '/user/index.php'); exit; }
// A teljes posztkezelők (vezetők/adminok) az admin felületen kezelik a bejegyzéseket
if (canManagePosts()) { header('Location: ' . BASE_URL . '/admin/posts.php'); exit; }

$pdo = getDb();
ensurePublicSchema($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

// Csak a saját bejegyzések (a teljes posztkezelők az admin felületen kezelik az összeset)
$stmt = $pdo->prepare("SELECT * FROM posts WHERE created_by = ? ORDER BY created_at DESC");
$stmt->execute([getCurrentUserId()]);
$posts = $stmt->fetchAll();

$pageTitle  = 'Bejegyzéseim';
$activePage = 'posts';
include __DIR__ . '/../includes/user-header.php';
?>

<?php if ($flash_success): ?><div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div><?php endif; ?>
<?php if ($flash_error): ?><div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div><?php endif; ?>

<div class="page-header">
  <h1>Bejegyzéseim</h1>
  <a href="<?= BASE_URL ?>/user/post-edit.php?new=1" class="btn btn-primary btn-sm">+ Új bejegyzés</a>
</div>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Cím</th><th>Kategória</th><th>Dátum</th><th>Állapot</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($posts)): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">📝</div><p>Még nincs bejegyzésed. Hozd létre az elsőt!</p></div></td></tr>
        <?php else: foreach ($posts as $p): ?>
        <tr>
          <td style="font-weight:600;"><?= e($p['title']) ?></td>
          <td><span class="badge <?= $p['category']==='beszmolok' ? 'badge-active' : 'badge-overdue' ?>"><?= $p['category']==='beszmolok' ? 'Élményblog' : 'Hírek' ?></span></td>
          <td style="white-space:nowrap;font-size:13px;color:var(--text-muted);"><?= e((new DateTime($p['created_at']))->format('Y.m.d')) ?></td>
          <td>
            <?php
              $st = $p['approval_status'] ?? 'draft';
              if ($st === 'pending')                              echo '<span class="badge badge-overdue">Jóváhagyásra vár</span>';
              elseif ($st === 'approved' && !empty($p['published'])) echo '<span class="badge badge-active">Publikálva</span>';
              elseif ($st === 'approved')                          echo '<span class="badge badge-active">Jóváhagyva</span>';
              else                                                 echo '<span class="badge badge-inactive">Vázlat</span>';
            ?>
          </td>
          <td class="td-actions" style="white-space:nowrap;text-align:right;">
            <?php if (!empty($p['published'])): ?>
              <a href="<?= BASE_URL ?>/public/post.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" class="btn btn-ghost btn-sm">Megnézem</a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/user/post-edit.php?id=<?= (int)$p['id'] ?>" class="btn btn-secondary btn-sm">Szerkesztés</a>
            <form method="post" action="<?= BASE_URL ?>/actions/post-delete.php" style="display:inline;margin:0;" onsubmit="return confirm('Biztosan törlöd ezt a bejegyzést?')">
              <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
              <input type="hidden" name="ctx" value="user">
              <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">Törlés</button>
            </form>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
