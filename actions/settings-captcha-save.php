<?php
/**
 * Google reCAPTCHA kulcsok mentése (admin → Beállítások).
 */
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/app-settings-schema.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
ensureAppSettingsSchema($pdo);

$siteKey = trim($_POST['recaptcha_site_key'] ?? '');
$secret  = $_POST['recaptcha_secret'] ?? '';

saveSetting($pdo, 'recaptcha_site_key', $siteKey);

// A titkos kulcsot csak akkor frissítjük, ha most adtak meg újat;
// a "törlés" jelölőnégyzettel viszont ki lehet üríteni.
if (!empty($_POST['recaptcha_clear_secret'])) {
    saveSetting($pdo, 'recaptcha_secret', '');
} elseif (trim($secret) !== '') {
    saveSetting($pdo, 'recaptcha_secret', trim($secret));
}

flash('success', 'reCAPTCHA beállítások mentve.');
header('Location: ' . BASE_URL . '/admin/settings.php');
exit;
