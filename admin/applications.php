<?php
session_start();
require_once __DIR__ . '/../includes/config.php';
$allowedStatuses = ['pending', 'approved', 'rejected', 'all'];
$status = in_array($_GET['status'] ?? '', $allowedStatuses, true) ? $_GET['status'] : 'pending';
header('Location: ' . BASE_URL . '/admin/members.php?tab=applications&status=' . $status);
exit;
