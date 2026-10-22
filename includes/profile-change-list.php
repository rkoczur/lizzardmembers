<?php
/** Függő lakcím / telefonszám módosítások (admin/members.php?tab=changes). Elvárt: $profileChanges */
?>
<?php if (empty($profileChanges)): ?>
  <div class="card"><div class="card-body">
    <div class="empty-state"><p>Nincs jóváhagyásra váró adatmódosítás.</p></div>
  </div></div>
<?php else: ?>
  <div class="pchg-list">
    <?php foreach ($profileChanges as $req): ?>
      <div class="card pchg-card">
        <div class="card-header">
          <h2>
            <a href="<?= BASE_URL ?>/admin/member-detail.php?id=<?= (int)$req['user_id'] ?>">
              <?= e($req['lastname'] . ' ' . $req['firstname']) ?>
            </a>
          </h2>
          <span class="td-sub"><?= e((new DateTime($req['created_at']))->format('Y.m.d H:i')) ?></span>
        </div>
        <div class="card-body">
          <div class="pchg-grid">
            <div class="pchg-head">Adat</div>
            <div class="pchg-head">Jelenlegi</div>
            <div class="pchg-head">Kért</div>
            <?php foreach (PROFILE_CHANGE_FIELDS as $key => $label):
              $cur = (string)($req['cur_' . $key] ?? '');
              $new = (string)($req[$key] ?? '');
              $changed = $cur !== $new; ?>
              <div class="pchg-label"><?= e($label) ?></div>
              <div class="pchg-old<?= $changed ? ' is-changed' : '' ?>"><?= e($cur ?: '—') ?></div>
              <div class="pchg-new<?= $changed ? ' is-changed' : '' ?>"><?= e($new ?: '—') ?></div>
            <?php endforeach; ?>
          </div>
          <form method="post" action="<?= BASE_URL ?>/actions/profile-change-process.php" class="pchg-actions">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
            <button type="submit" name="action" value="reject" class="btn btn-ghost btn-sm">Elutasítás</button>
            <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Jóváhagyás</button>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
