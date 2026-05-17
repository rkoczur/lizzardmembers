<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();

$pdo = getDb();

// Accept member IDs via POST (from members list) or GET (re-display after error)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['member_ids'])) {
    $ids = array_values(array_filter(array_map('intval', $_POST['member_ids'] ?? [])));
    $_SESSION['compose_ids'] = $ids;
} elseif (isset($_SESSION['compose_ids'])) {
    $ids = $_SESSION['compose_ids'];
} else {
    $ids = [];
}

if (empty($ids)) {
    flash('error', 'Nem jelölt ki egy tagot sem.');
    header('Location: ' . BASE_URL . '/admin/members.php');
    exit;
}

$flash_error   = getFlash('error');
$flash_success = getFlash('success');

$ph      = rtrim(str_repeat('?,', count($ids)), ',');
$members = $pdo->prepare("SELECT id, firstname, lastname, email FROM users WHERE id IN ($ph) ORDER BY lastname, firstname");
$members->execute($ids);
$members = $members->fetchAll();

$MERGE_FIELDS = [
    ['tag' => '{{nev}}',           'label' => 'Teljes név'],
    ['tag' => '{{vezeteknev}}',     'label' => 'Vezetéknév'],
    ['tag' => '{{keresztnev}}',     'label' => 'Keresztnév'],
    ['tag' => '{{email}}',          'label' => 'E-mail cím'],
    ['tag' => '{{felhasznalonev}}', 'label' => 'Felhasználónév'],
    ['tag' => '{{szint}}',          'label' => 'Szint neve'],
    ['tag' => '{{pontok}}',         'label' => 'Pontszám'],
    ['tag' => '{{varos}}',          'label' => 'Város'],
    ['tag' => '{{tagsag_kezdete}}', 'label' => 'Tagság kezdete'],
];

$pageTitle  = 'Tömeges e-mail küldés';
$activePage = 'members';
include __DIR__ . '/../includes/admin-header.php';
?>

<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>
<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>

<div class="page-header">
  <div class="flex items-center gap-2">
    <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary btn-sm">← Vissza</a>
    <h1>Tömeges e-mail küldés</h1>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;">

  <!-- Bal: Címzettek -->
  <div class="card">
    <div class="card-header">
      <h2>Címzettek</h2>
      <span style="font-size:13px;color:var(--text-muted);"><?= count($members) ?> fő</span>
    </div>
    <div style="max-height:420px;overflow-y:auto;">
      <table style="width:100%;border-collapse:collapse;">
        <tbody>
          <?php foreach ($members as $m): ?>
          <tr style="border-bottom:1px solid var(--border);">
            <td style="padding:8px 14px;">
              <div style="font-size:13px;font-weight:600;"><?= e($m['lastname'] . ' ' . $m['firstname']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);"><?= e($m['email']) ?></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Jobb: Szerkesztő -->
  <div class="card">
    <div class="card-header"><h2>E-mail összeállítása</h2></div>
    <div class="card-body">
      <form method="post" action="<?= BASE_URL ?>/actions/bulk-email-send.php" id="compose-form">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
        <?php foreach ($ids as $uid): ?>
          <input type="hidden" name="member_ids[]" value="<?= (int)$uid ?>">
        <?php endforeach; ?>

        <div class="form-group" style="margin-bottom:16px;">
          <label>Tárgy</label>
          <input type="text" name="subject" id="subject" required placeholder="Az e-mail tárgya">
        </div>

        <div class="form-group" style="margin-bottom:8px;">
          <label>Beilleszthető mezők</label>
          <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
            <?php foreach ($MERGE_FIELDS as $f): ?>
            <button type="button" class="btn btn-ghost btn-sm merge-field-btn"
                    data-tag="<?= e($f['tag']) ?>"
                    style="font-family:monospace;font-size:11px;"
                    title="Kattintson a szövegmezőbe illesztéshez">
              <?= e($f['tag']) ?>
              <span style="font-family:sans-serif;font-size:10px;color:var(--text-muted);margin-left:2px;"><?= e($f['label']) ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label>Szöveg <span style="color:var(--text-muted);font-size:12px;font-weight:400;">(HTML is használható)</span></label>
          <textarea name="body" id="body" rows="14" required
                    placeholder="Kedves {{nev}}!&#10;&#10;..."
                    style="font-family:monospace;font-size:13px;resize:vertical;"></textarea>
        </div>

        <div class="flex gap-2" style="margin-top:16px;flex-wrap:wrap;align-items:center;">
          <button type="submit" class="btn btn-primary" id="send-btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="15" height="15">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Küldés <?= count($members) ?> főnek
          </button>
          <a href="<?= BASE_URL ?>/admin/members.php" class="btn btn-secondary">Mégse</a>
          <span id="send-status" style="font-size:13px;color:var(--text-muted);display:none;">Küldés folyamatban…</span>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
(function () {
  // Insert merge field at cursor in textarea
  document.querySelectorAll('.merge-field-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ta  = document.getElementById('body');
      var tag = btn.getAttribute('data-tag');
      var s   = ta.selectionStart, e = ta.selectionEnd;
      ta.value = ta.value.substring(0, s) + tag + ta.value.substring(e);
      ta.selectionStart = ta.selectionEnd = s + tag.length;
      ta.focus();
    });
  });

  // Disable send button while submitting
  document.getElementById('compose-form').addEventListener('submit', function () {
    document.getElementById('send-btn').disabled = true;
    document.getElementById('send-status').style.display = '';
  });
})();
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
