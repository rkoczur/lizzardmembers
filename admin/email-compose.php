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

<div id="compose-grid" style="display:grid;grid-template-columns:1fr 2fr;gap:20px;align-items:start;">

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
        </div>

        <!-- Küldési folyamatjelző (AJAX) -->
        <div id="send-progress" class="send-progress" hidden>
          <div class="send-progress-head">
            <span id="send-progress-label" class="send-progress-label">Küldés folyamatban…</span>
            <span id="send-progress-count" class="send-progress-count">0/0</span>
          </div>
          <div class="send-progress-track">
            <div id="send-progress-fill" class="send-progress-fill"></div>
          </div>
          <div id="send-progress-note" class="send-progress-note"></div>
          <ul id="send-failed-list" class="send-failed-list" hidden></ul>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
(function () {
  var BASE = <?= json_encode(BASE_URL) ?>;
  var CSRF = <?= json_encode(csrfToken()) ?>;
  var BATCH_SIZE = 3;
  var RETRY_WAIT = 30; // mp

  // Beilleszthető mező a kurzorhoz
  document.querySelectorAll('.merge-field-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var ta  = document.getElementById('body');
      var tag = btn.getAttribute('data-tag');
      var s = ta.selectionStart, e = ta.selectionEnd;
      ta.value = ta.value.substring(0, s) + tag + ta.value.substring(e);
      ta.selectionStart = ta.selectionEnd = s + tag.length;
      ta.focus();
    });
  });

  var form    = document.getElementById('compose-form');
  var sendBtn = document.getElementById('send-btn');
  var panel   = document.getElementById('send-progress');
  var label   = document.getElementById('send-progress-label');
  var count   = document.getElementById('send-progress-count');
  var fill    = document.getElementById('send-progress-fill');
  var note    = document.getElementById('send-progress-note');
  var failUl  = document.getElementById('send-failed-list');

  var total = 0, processed = 0, sentOk = 0, failedItems = [], aborted = false, abortError = '';

  function setProgress() {
    count.textContent = processed + '/' + total;
    fill.style.width = (total ? Math.round(processed / total * 100) : 0) + '%';
  }

  function postJson(url, params) {
    var fd = new FormData();
    fd.append('csrf_token', CSRF);
    Object.keys(params).forEach(function (k) {
      var v = params[k];
      if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
      else fd.append(k, v);
    });
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function sendBatches(token, ids, countProgress) {
    var i = 0;
    function next() {
      if (i >= ids.length || aborted) return Promise.resolve();
      var slice = ids.slice(i, i + BATCH_SIZE);
      i += BATCH_SIZE;
      return postJson(BASE + '/actions/bulk-email-batch.php', { token: token, batch_ids: slice })
        .then(function (res) {
          if (!res.ok) throw new Error(res.error || 'Ismeretlen szerverhiba.');
          res.results.forEach(function (r) {
            if (countProgress) processed++;
            if (r.ok) sentOk++; else failedItems.push(r);
          });
          if (countProgress) setProgress();
          // Első SMTP-hiba → leállítjuk a kiküldést, nincs újrapróbálkozás
          if (res.stopped) { aborted = true; abortError = res.stopError || 'SMTP hiba'; return Promise.resolve(); }
          return next();
        });
    }
    return next();
  }

  function renderFailures() {
    if (!failedItems.length) { failUl.hidden = true; return; }
    failUl.hidden = false;
    failUl.innerHTML = '';
    failedItems.forEach(function (f) {
      var li = document.createElement('li');
      li.textContent = (f.name || ('#' + f.id)) + ' — ' + (f.error || 'sikertelen');
      failUl.appendChild(li);
    });
  }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var subject = document.getElementById('subject').value.trim();
    var body    = document.getElementById('body').value.trim();
    if (!subject || !body) { alert('Töltse ki a tárgyat és a szöveget is.'); return; }

    sendBtn.disabled = true;
    panel.hidden = false;
    fill.classList.remove('is-done', 'is-warn');
    label.textContent = 'Küldés előkészítése…';
    note.textContent = '';
    failUl.hidden = true;

    var ids = Array.prototype.map.call(
      form.querySelectorAll('input[name="member_ids[]"]'),
      function (el) { return el.value; }
    );

    postJson(BASE + '/actions/bulk-email-prepare.php', { member_ids: ids, subject: subject, body: body })
      .then(function (res) {
        if (!res.ok) throw new Error(res.error || 'Az előkészítés sikertelen.');
        total = res.total; processed = 0; sentOk = 0; failedItems = []; aborted = false; abortError = '';
        label.textContent = 'Küldés folyamatban…';
        setProgress();
        return sendBatches(res.token, res.ids, true);
      })
      .then(function () {
        renderFailures();
        if (aborted) {
          label.textContent = 'Megszakadt';
          fill.classList.add('is-warn');
          note.textContent = 'A küldés leállt egy SMTP-hiba miatt: ' + abortError + '. Eddig ' + sentOk + ' e-mail ment ki.';
        } else {
          label.textContent = 'Kész';
          fill.classList.add(failedItems.length ? 'is-warn' : 'is-done');
          note.textContent = sentOk + ' sikeres küldés' + (failedItems.length ? (', ' + failedItems.length + ' sikertelen.') : '.');
        }
      })
      .catch(function (err) {
        label.textContent = 'Hiba';
        note.textContent = err.message || 'Váratlan hiba történt.';
        sendBtn.disabled = false;
      });
  });
})();
</script>

<?php include __DIR__ . '/../includes/admin-footer.php'; ?>
