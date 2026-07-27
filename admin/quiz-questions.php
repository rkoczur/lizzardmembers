<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/quiz-schema.php';
requireAdmin();

$pdo = getDb();
ensureQuizSchema($pdo);

$birds = $pdo->query("SELECT id, name, continents, (image IS NOT NULL AND image <> '') AS has_image FROM quiz_birds ORDER BY name")->fetchAll();
foreach ($birds as &$b) {
    $b['continents'] = json_decode((string)$b['continents'], true) ?: [];
}
unset($b);

$mountains = $pdo->query("SELECT id, name, country, (image IS NOT NULL AND image <> '') AS has_image FROM quiz_mountains ORDER BY name")->fetchAll();

$allContinents = quizAllContinents($pdo);
$allCountries  = quizAllCountries($pdo);

$flash_success = getFlash('success');
$flash_error   = getFlash('error');

$pageTitle  = 'Kvíz kérdések';
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
    <h1>Kvíz kérdések</h1>
    <a href="<?= BASE_URL ?>/admin/quiz.php" class="btn btn-secondary btn-sm">← Vissza a kvízhez</a>
</div>
<p style="color:var(--text-muted,#7a7269);margin-bottom:20px;">
    Itt a madár / hegycsúcs minden adata szerkeszthető (név, fesztáv/magasság, színek/ország stb.), illetve a hozzá tartozó kép cserélhető.
</p>

<details style="margin-bottom:20px;">
    <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--text-muted,#7a7269);">Karbantartás</summary>
    <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
        <form action="<?= BASE_URL ?>/actions/quiz-fix-oceania.php" method="post"
              onsubmit="return confirm('Biztosan lefuttatod az „Óceánia” → „Ausztrália-Óceánia” javítást?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">🔧 „Óceánia” → „Ausztrália-Óceánia” javítása</button>
        </form>
        <form action="<?= BASE_URL ?>/actions/quiz-sync-source-data.php" method="post"
              onsubmit="return confirm('Biztosan lefuttatod a birds.json / mountain_peaks.csv forrásfájlokból hiányzó madarak/hegyek pótlását?');">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button type="submit" class="btn btn-secondary btn-sm">🔄 Hiányzó madarak/hegyek pótlása a forrásfájlokból</button>
        </form>
    </div>
</details>

<details class="card" style="margin-bottom:16px;">
    <summary style="cursor:pointer;padding:14px 20px;font-weight:600;list-style:none;display:flex;align-items:center;gap:8px;">
        🐦 Madarak (<?= count($birds) ?>)
    </summary>
    <div class="card-body" style="border-top:1px solid var(--border);">
        <div class="flex items-center gap-2" style="justify-content:space-between;margin-bottom:10px;">
            <select id="quiz-bird-continent-filter">
                <option value="">Összes kontinens</option>
                <?php foreach ($allContinents as $continent): ?>
                    <option value="<?= e($continent) ?>"><?= e($continent) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="quiz-bird-search" placeholder="Madár keresése…">
            </div>
        </div>
        <table>
            <thead>
                <tr><th style="width:60px;">Kép</th><th>Név</th><th>Kontinensek</th><th style="width:100px;"></th></tr>
            </thead>
            <tbody id="quiz-bird-table">
            <?php foreach ($birds as $b): ?>
                <tr data-continents="<?= e(implode('|', $b['continents'])) ?>">
                    <td>
                        <?php if ($b['has_image']): ?>
                            <span title="Van kép" style="font-size:20px;">🖼️</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted,#7a7269);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($b['name']) ?></td>
                    <td><?= e(implode(', ', $b['continents'])) ?></td>
                    <td><a href="<?= BASE_URL ?>/admin/quiz-question-edit.php?type=bird&id=<?= (int)$b['id'] ?>" class="btn btn-secondary btn-sm">Szerkesztés</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>

<details class="card">
    <summary style="cursor:pointer;padding:14px 20px;font-weight:600;list-style:none;display:flex;align-items:center;gap:8px;">
        ⛰️ Hegycsúcsok (<?= count($mountains) ?>)
    </summary>
    <div class="card-body" style="border-top:1px solid var(--border);">
        <div class="flex items-center gap-2" style="justify-content:space-between;margin-bottom:10px;">
            <select id="quiz-mountain-country-filter">
                <option value="">Összes ország</option>
                <?php foreach ($allCountries as $country): ?>
                    <option value="<?= e($country) ?>"><?= e($country) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="quiz-mountain-search" placeholder="Hegycsúcs keresése…">
            </div>
        </div>
        <table>
            <thead>
                <tr><th style="width:60px;">Kép</th><th>Név</th><th>Ország</th><th style="width:100px;"></th></tr>
            </thead>
            <tbody id="quiz-mountain-table">
            <?php foreach ($mountains as $m): ?>
                <tr data-country="<?= e($m['country']) ?>">
                    <td>
                        <?php if ($m['has_image']): ?>
                            <span title="Van kép" style="font-size:20px;">🖼️</span>
                        <?php else: ?>
                            <span style="color:var(--text-muted,#7a7269);">—</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($m['name']) ?></td>
                    <td><?= e($m['country']) ?></td>
                    <td><a href="<?= BASE_URL ?>/admin/quiz-question-edit.php?type=mountain&id=<?= (int)$m['id'] ?>" class="btn btn-secondary btn-sm">Szerkesztés</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</details>
<?php
include __DIR__ . '/../includes/admin-footer.php';
