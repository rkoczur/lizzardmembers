<?php
/**
 * Meghirdetett túra adatlap központi szövegei (admin → Beállítások):
 * „FONTOS!” blokk és a díj megjegyzés alapszövege. Üres mező → beépített alapszöveg.
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

saveSetting($pdo, 'tour_important_text', trim($_POST['tour_important_text'] ?? ''));
saveSetting($pdo, 'tour_fee_note_default', trim($_POST['tour_fee_note_default'] ?? ''));

flash('success', 'A túra adatlap szövegei elmentve.');
header('Location: ' . BASE_URL . '/admin/settings.php#tour-texts');
exit;
