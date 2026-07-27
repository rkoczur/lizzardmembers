<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireAdmin();

$pdo  = getDb();
$type = (string)($_GET['type'] ?? '');
$id   = (int)($_GET['id'] ?? 0);

if (!in_array($type, ['bird', 'mountain'], true) || $id <= 0) {
    flash('error', 'Érvénytelen kérdés.');
    header('Location: ' . BASE_URL . '/admin/quiz-questions.php');
    exit;
}

$item = $type === 'bird' ? quizGetBird($pdo, $id) : quizGetMountain($pdo, $id);

if (!$item) {
    flash('error', 'A kérdés nem található.');
    header('Location: ' . BASE_URL . '/admin/quiz-questions.php');
    exit;
}

$allColors     = $type === 'bird' ? quizAllColors($pdo) : [];
$allContinents = $type === 'bird' ? quizAllContinents($pdo) : [];
$allCountries  = $type === 'mountain' ? quizAllCountries($pdo) : [];

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = ($type === 'bird' ? 'Madár' : 'Hegycsúcs') . ' szerkesztése';
$activePage = 'quiz-questions';
include __DIR__ . '/../includes/admin-header.php';
?>
<?php if ($flash_success): ?>
  <div class="alert alert-success" data-auto-dismiss><?= e($flash_success) ?></div>
<?php endif; ?>
<?php if ($flash_error): ?>
  <div class="alert alert-error" data-auto-dismiss><?= e($flash_error) ?></div>
<?php endif; ?>

<div class="page-header">
    <h1><?= $type === 'bird' ? '🐦' : '⛰️' ?> <?= e($item['name']) ?> szerkesztése</h1>
    <a href="<?= BASE_URL ?>/admin/quiz-questions.php" class="btn btn-secondary btn-sm">← Vissza a listához</a>
</div>

<form action="<?= BASE_URL ?>/actions/quiz-question-update.php" method="post" enctype="multipart/form-data" class="card" style="max-width:640px;padding:24px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
    <input type="hidden" name="type" value="<?= e($type) ?>">
    <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

    <div class="form-group">
        <label>Név (magyar)</label>
        <input type="text" name="name" value="<?= e($item['name']) ?>" required maxlength="190">
    </div>

    <?php if ($type === 'bird'): ?>
        <div class="form-group">
            <label>Fesztáv (cm)</label>
            <input type="number" name="wingspan_cm" value="<?= (int)$item['wingspan_cm'] ?>" min="1" max="999" required>
        </div>

        <div class="form-group">
            <label>Színek</label><br>
            <div style="display:flex;flex-wrap:wrap;gap:10px 16px;margin:6px 0;">
                <?php foreach ($allColors as $color): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:normal;">
                        <input type="checkbox" name="colors[]" value="<?= e($color) ?>" <?= in_array($color, $item['colors'], true) ? 'checked' : '' ?>>
                        <?= e($color) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="colors_extra" placeholder="Új szín(ek), vesszővel elválasztva">
            <small style="color:var(--text-muted,#7a7269);">Legalább egy szín kiválasztása vagy megadása kötelező.</small>
        </div>

        <div class="form-group">
            <label>Kontinensek</label><br>
            <div style="display:flex;flex-wrap:wrap;gap:10px 16px;margin:6px 0;">
                <?php foreach ($allContinents as $continent): ?>
                    <label style="display:flex;align-items:center;gap:6px;font-weight:normal;">
                        <input type="checkbox" name="continents[]" value="<?= e($continent) ?>" <?= in_array($continent, $item['continents'], true) ? 'checked' : '' ?>>
                        <?= e($continent) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <input type="text" name="continents_extra" placeholder="Új kontinens(ek), vesszővel elválasztva">
            <small style="color:var(--text-muted,#7a7269);">Legalább egy kontinens kiválasztása vagy megadása kötelező.</small>
        </div>

        <div class="form-group">
            <label>Latin név</label>
            <input type="text" name="latin_name" value="<?= e((string)$item['latin_name']) ?>" maxlength="190">
        </div>

        <div class="form-group">
            <label>Étrend</label>
            <textarea name="diet" rows="2"><?= e((string)$item['diet']) ?></textarea>
        </div>

        <div class="form-group">
            <label>Érdekesség</label>
            <textarea name="fun_fact" rows="3"><?= e((string)$item['fun_fact']) ?></textarea>
        </div>
    <?php else: ?>
        <div class="form-group">
            <label>Magasság (m)</label>
            <input type="number" name="elevation_m" value="<?= (int)$item['elevation_m'] ?>" min="1" max="99999" required>
        </div>

        <div class="form-group">
            <label>Ország</label>
            <input type="text" name="country" list="quiz-countries" value="<?= e($item['country']) ?>" required maxlength="190">
            <datalist id="quiz-countries">
                <?php foreach ($allCountries as $country): ?>
                    <option value="<?= e($country) ?>">
                <?php endforeach; ?>
            </datalist>
        </div>

        <div class="form-group">
            <label>Hegység</label>
            <input type="text" name="mountain_range" value="<?= e((string)$item['mountain_range']) ?>" maxlength="190">
        </div>

        <div class="form-group">
            <label>Érdekesség</label>
            <textarea name="fun_fact" rows="3"><?= e((string)$item['fun_fact']) ?></textarea>
        </div>
    <?php endif; ?>

    <div class="form-group">
        <label>Jelenlegi kép</label><br>
        <?php if ($item['image']): ?>
            <img src="<?= BASE_URL ?>/kviz/<?= e($item['image']) ?>" alt="" style="max-width:100%;width:420px;height:auto;border-radius:8px;margin:8px 0;">
        <?php else: ?>
            <p style="color:var(--text-muted,#7a7269);">Nincs kép beállítva.</p>
        <?php endif; ?>
    </div>

    <div class="form-group">
        <label>Kép cseréje</label>
        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
        <small style="color:var(--text-muted,#7a7269);">JPG, PNG, GIF vagy WEBP, max. 2 MB. Hagyd üresen, ha nem szeretnéd cserélni.</small>
    </div>

    <div class="form-actions" style="margin-top:16px;">
        <button type="submit" class="btn btn-primary">Mentés</button>
    </div>
</form>
<?php
include __DIR__ . '/../includes/admin-footer.php';
