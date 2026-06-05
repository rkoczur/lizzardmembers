<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireAdmin();
verifyCsrf();

$pdo = getDb();
$id  = (int)($_POST['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/admin/documents.php'); exit; }

$stmt = $pdo->prepare("SELECT filename FROM documents WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$filename = $stmt->fetchColumn();
if ($filename) @unlink(__DIR__ . '/../assets/uploads/docs/' . $filename);

$pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);

flash('success', 'Dokumentum törölve.');
header('Location: ' . BASE_URL . '/admin/documents.php');
exit;
