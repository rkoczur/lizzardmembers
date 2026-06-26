<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/public-schema.php';
requireLogin(); verifyCsrf();
if (!canCreatePosts()) { flash('error', 'Nincs jogosultságod ehhez.'); header('Location: ' . BASE_URL . '/user/index.php'); exit; }

$pdo = getDb();
ensurePublicSchema($pdo);

// Tag-portál kontextus: a „bejegyzés létrehozása” joggal rendelkező tag a saját felületéről ment.
$isUserCtx = (($_POST['ctx'] ?? '') === 'user');
$canManageAll = canManagePosts(); // teljes posztkezelő (vezető) — más szerző posztját is szerkesztheti
$backNew  = $isUserCtx ? BASE_URL . '/user/post-edit.php?new=1' : BASE_URL . '/admin/post-detail.php?new=1';
$listUrl  = $isUserCtx ? BASE_URL . '/user/posts.php'           : null;

$id       = (int)($_POST['id'] ?? 0);
$isNew    = ($id === 0);
$title    = trim($_POST['title']    ?? '');
$slug     = trim($_POST['slug']     ?? '');
$category = in_array($_POST['category'] ?? '', ['hirek','beszmolok']) ? $_POST['category'] : 'hirek';
$excerpt  = trim($_POST['excerpt']  ?? '');
$metaKeywords = trim($_POST['meta_keywords'] ?? '');
$coverAlt = trim($_POST['cover_alt'] ?? '');
$body     = trim($_POST['body']     ?? '');
$published = !empty($_POST['published']) ? 1 : 0;
// Szerző kézi kiválasztása csak teljes adminnak engedett
$authorId  = (isAdmin() && !empty($_POST['author_id'])) ? (int)$_POST['author_id'] : null;

// Jóváhagyási állapot: a posztkezelők (vezetők) közvetlenül publikálnak; a sima tag bejegyzése
// admin jóváhagyásra vár, és addig nem publikus.
if ($canManageAll) {
    $approvalStatus = 'approved';
} else {
    $published      = 0; // tag közvetlenül nem publikálhat
    $approvalStatus = !empty($_POST['submit_approval']) ? 'pending' : 'draft';
}
$successMsg = $canManageAll
    ? ($isNew ? 'Poszt sikeresen létrehozva.' : 'Poszt sikeresen mentve.')
    : ($approvalStatus === 'pending'
        ? 'Bejegyzésed beküldve jóváhagyásra. Az admin jóváhagyása után jelenik meg a weboldalon.'
        : 'Piszkozat elmentve.');
$createdAtRaw = trim($_POST['created_at'] ?? '');
$createdAt = $createdAtRaw ? date('Y-m-d H:i:s', strtotime($createdAtRaw)) : null;

// Validate slug
$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($slug));

if (!$title || !$slug || !$body) {
    flash('error', 'Cím, slug és tartalom megadása kötelező.');
    $redir = $isNew ? $backNew : (($isUserCtx ? BASE_URL . '/user/post-edit.php?id=' : BASE_URL . '/admin/post-detail.php?id=') . $id);
    header('Location: ' . $redir); exit;
}

// Cover image upload
$POST_UPLOAD_DIR = __DIR__ . '/../assets/uploads/posts/';
if (!is_dir($POST_UPLOAD_DIR)) mkdir($POST_UPLOAD_DIR, 0755, true);

$coverImg = $isNew ? null : null;
if (!$isNew) {
    $old = $pdo->prepare("SELECT cover_img, created_by FROM posts WHERE id = ? LIMIT 1");
    $old->execute([$id]);
    $oldRow = $old->fetch();
    if (!$oldRow) { header('Location: ' . ($listUrl ?? BASE_URL . '/admin/posts.php')); exit; }
    // Ownership: aki nem teljes posztkezelő, csak a SAJÁT bejegyzését szerkesztheti
    if (!$canManageAll && (int)($oldRow['created_by'] ?? 0) !== getCurrentUserId()) {
        flash('error', 'Csak a saját bejegyzésedet szerkesztheted.');
        header('Location: ' . ($listUrl ?? BASE_URL . '/admin/posts.php')); exit;
    }
    $coverImg = ($oldRow['cover_img'] ?: null);
}

if (!empty($_POST['delete_cover']) && $coverImg) {
    @unlink($POST_UPLOAD_DIR . $coverImg);
    $coverImg = null;
}

if (!empty($_FILES['cover_img']['tmp_name']) && $_FILES['cover_img']['error'] === UPLOAD_ERR_OK) {
    $allowedMimes = ['image/jpeg','image/png','image/webp'];
    $size         = (int)($_FILES['cover_img']['size'] ?? 0);
    $mime         = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['cover_img']['tmp_name']);
    $ext          = match($mime) { 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', default => '' };
    if ($ext && $size <= 4 * 1024 * 1024 && in_array($mime, $allowedMimes, true)) {
        $newFile = 'post_' . ($isNew ? 'new' : $id) . '_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['cover_img']['tmp_name'], $POST_UPLOAD_DIR . $newFile)) {
            if ($coverImg) @unlink($POST_UPLOAD_DIR . $coverImg);
            $coverImg = $newFile;
        }
    }
}

if ($isNew) {
    // Check slug uniqueness
    $exists = $pdo->prepare("SELECT id FROM posts WHERE slug = ? LIMIT 1");
    $exists->execute([$slug]);
    if ($exists->fetch()) {
        flash('error', 'Ez a slug már foglalt, válassz másikat.');
        header('Location: ' . $backNew); exit;
    }
    $insertCreatedAt = $createdAt ?? date('Y-m-d H:i:s');
    $pdo->prepare("INSERT INTO posts (title, slug, category, excerpt, meta_keywords, body, cover_img, cover_alt, published, approval_status, created_by, author_id, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$title, $slug, $category, $excerpt ?: null, $metaKeywords ?: null, $body, $coverImg, $coverAlt ?: null, $published, $approvalStatus, getCurrentUserId(), $authorId, $insertCreatedAt]);
    $newId = (int)$pdo->lastInsertId();
    // Fix cover filename with real id
    if ($coverImg && strpos($coverImg, '_new_') !== false) {
        $fixed = str_replace('_new_', '_' . $newId . '_', $coverImg);
        rename($POST_UPLOAD_DIR . $coverImg, $POST_UPLOAD_DIR . $fixed);
        $pdo->prepare("UPDATE posts SET cover_img = ? WHERE id = ?")->execute([$fixed, $newId]);
    }
    flash('success', $successMsg);
    header('Location: ' . ($listUrl ?? BASE_URL . '/admin/post-detail.php?id=' . $newId));
} else {
    $updateCreatedAt = $createdAt ?? date('Y-m-d H:i:s');
    if (isAdmin()) {
        // Admin a szerzőt is állíthatja
        $pdo->prepare("UPDATE posts SET title=?, slug=?, category=?, excerpt=?, meta_keywords=?, body=?, cover_img=?, cover_alt=?, published=?, approval_status=?, author_id=?, created_at=? WHERE id=?")
            ->execute([$title, $slug, $category, $excerpt ?: null, $metaKeywords ?: null, $body, $coverImg, $coverAlt ?: null, $published, $approvalStatus, $authorId, $updateCreatedAt, $id]);
    } else {
        // Nem-admin szerkesztő (vezető vagy tag) — a szerzőt nem módosítja
        $pdo->prepare("UPDATE posts SET title=?, slug=?, category=?, excerpt=?, meta_keywords=?, body=?, cover_img=?, cover_alt=?, published=?, approval_status=?, created_at=? WHERE id=?")
            ->execute([$title, $slug, $category, $excerpt ?: null, $metaKeywords ?: null, $body, $coverImg, $coverAlt ?: null, $published, $approvalStatus, $updateCreatedAt, $id]);
    }
    flash('success', $successMsg);
    header('Location: ' . ($listUrl ?? BASE_URL . '/admin/post-detail.php?id=' . $id));
}

// Tag jóváhagyásra küldte a bejegyzést → értesítő e-mail a posztkezelőknek
if (!$canManageAll && $approvalStatus === 'pending') {
    $savedPostId = $isNew ? ($newId ?? 0) : $id;
    try {
        require_once __DIR__ . '/../includes/app-settings-schema.php';
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/email-log-schema.php';
        ensureAppSettingsSchema($pdo);
        ensureEmailLogSchema($pdo);
        $smtp = getSmtpConfig($pdo);
        if (($smtp['host'] ?? '') !== '') {
            $authorName = $_SESSION['user_name'] ?? 'Egy tag';
            $proto      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $absBaseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL;
            $adminUrl   = $absBaseUrl . '/admin/posts.php';
            $subject    = 'Új bejegyzés jóváhagyásra vár: ' . $title;
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5efe4;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:32px 16px;">
            <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08);">
              <tr><td style="background:#1a3d39;padding:24px 32px;">
                <h1 style="color:#c8a84b;margin:0;font-size:22px;">' . APP_NAME . '</h1>
                <p style="color:#a8c5c2;margin:6px 0 0;font-size:14px;">Bejegyzés jóváhagyásra vár</p>
              </td></tr>
              <tr><td style="padding:28px 32px;">
                <p style="font-size:14px;color:#444;line-height:1.7;margin:0 0 16px;"><strong>' . htmlspecialchars($authorName, ENT_QUOTES) . '</strong> beküldött egy bejegyzést jóváhagyásra:</p>
                <table width="100%" style="background:#f5efe4;border-radius:8px;padding:16px;margin:0 0 18px;font-size:14px;">
                  <tr><td style="padding:4px 0;color:#666;">Cím:</td><td style="font-weight:600;">' . htmlspecialchars($title, ENT_QUOTES) . '</td></tr>
                  <tr><td style="padding:4px 0;color:#666;">Kategória:</td><td>' . ($category === 'beszmolok' ? 'Élményblog' : 'Hírek') . '</td></tr>
                </table>
                <div style="text-align:center;"><a href="' . $adminUrl . '" style="background:#29776F;color:#fff;text-decoration:none;padding:12px 28px;border-radius:6px;font-size:14px;font-weight:600;">Bejegyzések megnyitása</a></div>
              </td></tr>
              <tr><td style="padding:16px 32px;background:#f5f5f5;text-align:center;font-size:12px;color:#999;">' . APP_NAME . ' &bull; Automatikus értesítő</td></tr>
            </table></td></tr></table></body></html>';
            $mailer = new SmtpMailer($smtp);
            $admins = $pdo->query("SELECT id, email, firstname, lastname FROM users WHERE role IN ('admin','helyettes','kommunikacios') AND active = 1")->fetchAll();
            foreach ($admins as $a) {
                $rn = $a['lastname'] . ' ' . $a['firstname'];
                try {
                    $mailer->send($a['email'], $rn, $subject, $html);
                    logEmailEntry($pdo, (int)$a['id'], $a['email'], $rn, $subject, $html, 'post_pending_approval', 'sent');
                } catch (Throwable $ex) {
                    logEmailEntry($pdo, (int)$a['id'], $a['email'], $rn, $subject, $html, 'post_pending_approval', 'failed', $ex->getMessage());
                }
            }
        }
    } catch (Throwable $ex) {
        error_log('Post approval notification error: ' . $ex->getMessage());
    }
}
exit;
