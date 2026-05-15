<?php
session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// If DB already exists and tables are ready, redirect to login
if (databaseExists()) {
    $pdo = getDb();
    if ($pdo) {
        $res = $pdo->query("SHOW TABLES LIKE 'users'")->rowCount();
        if ($res > 0) {
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        }
    }
}

$error   = '';
$success = false;
$noServer = isset($_GET['error']) && $_GET['error'] === 'noserver';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$noServer) {
    $adminUser  = trim($_POST['admin_username'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_password'] ?? '';
    $adminPass2 = $_POST['admin_password2'] ?? '';
    $adminFirst = trim($_POST['admin_firstname'] ?? '');
    $adminLast  = trim($_POST['admin_lastname'] ?? '');

    if (!$adminUser || !$adminEmail || !$adminPass || !$adminFirst || !$adminLast) {
        $error = 'Minden mező kitöltése kötelező.';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Kérjük, adjon meg érvényes e-mail-címet.';
    } elseif (strlen($adminPass) < 6) {
        $error = 'A jelszónak legalább 6 karakter hosszúnak kell lennie.';
    } elseif ($adminPass !== $adminPass2) {
        $error = 'A jelszavak nem egyeznek.';
    } else {
        $pdo = getDbConnection(false);
        if (!$pdo) {
            $error = 'Nem sikerült csatlakozni a MySQL-kiszolgálóhoz. Ellenőrizze a gazdagépet, a portot, a felhasználót és a jelszót a config.ini fájlban.';
        } else {
            try {
                // Create database
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `" . DB_NAME . "`");

                // Create users table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
                    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `username`        VARCHAR(50)  NOT NULL,
                    `email`           VARCHAR(100) NOT NULL,
                    `password`        VARCHAR(255) NOT NULL,
                    `role`            ENUM('admin','user') NOT NULL DEFAULT 'user',
                    `firstname`       VARCHAR(50)  DEFAULT NULL,
                    `lastname`        VARCHAR(50)  DEFAULT NULL,
                    `dateofbirth`     DATE         DEFAULT NULL,
                    `zipcode`         VARCHAR(20)  DEFAULT NULL,
                    `city`            VARCHAR(100) DEFAULT NULL,
                    `address`         VARCHAR(255) DEFAULT NULL,
                    `phone`           VARCHAR(30)  DEFAULT NULL,
                    `tshirt_size`     ENUM('XS','S','M','L','XL','XXL','XXXL') DEFAULT NULL,
                    `emergency_name`  VARCHAR(100) DEFAULT NULL,
                    `emergency_relation` ENUM('szülő','gyermek','testvér','egyéb') DEFAULT NULL,
                    `emergency_phone` VARCHAR(30)  DEFAULT NULL,
                    `member_since`    DATE         DEFAULT NULL,
                    `last_payment`    DATE         DEFAULT NULL,
                    `profile_picture` VARCHAR(255) DEFAULT NULL,
                    `points`          INT UNSIGNED NOT NULL DEFAULT 0,
                    `level`           TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    `active`          TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_username` (`username`),
                    UNIQUE KEY `uq_email` (`email`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Create tours table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tours` (
                    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    `name`              VARCHAR(200) DEFAULT NULL,
                    `country`           VARCHAR(100) NOT NULL,
                    `region`            VARCHAR(100) DEFAULT NULL,
                    `tour_date`         DATE DEFAULT NULL,
                    `days`              TINYINT UNSIGNED NOT NULL DEFAULT 1,
                    `accommodation`     VARCHAR(100) DEFAULT NULL,
                    `total_km`          DECIMAL(8,1) DEFAULT NULL,
                    `total_elevation`   INT UNSIGNED DEFAULT NULL,
                    `participant_count` SMALLINT UNSIGNED DEFAULT NULL,
                    `points`            INT UNSIGNED NOT NULL DEFAULT 0,
                    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Create tour_members table
                $pdo->exec("CREATE TABLE IF NOT EXISTS `tour_members` (
                    `tour_id`    INT UNSIGNED NOT NULL,
                    `user_id`    INT UNSIGNED NOT NULL,
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`tour_id`, `user_id`),
                    FOREIGN KEY (`tour_id`) REFERENCES `tours`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Create admin user
                $hash = password_hash($adminPass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO `users`
                    (username, email, password, role, firstname, lastname, member_since, active)
                    VALUES (?, ?, ?, 'admin', ?, ?, CURDATE(), 1)");
                $stmt->execute([$adminUser, $adminEmail, $hash, $adminFirst, $adminLast]);

                header('Location: ' . BASE_URL . '/login.php?setup=1');
                exit;
            } catch (PDOException $e) {
                $error = 'Adatbázis-hiba: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="hu">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Beállítás — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="setup-page">
  <div class="setup-card">
    <div class="setup-icon">🦎</div>
    <h1><?= APP_NAME ?></h1>

    <?php if ($noServer): ?>
      <p class="setup-desc" style="color:var(--danger);">
        <strong>Nem sikerült csatlakozni a MySQL-kiszolgálóhoz.</strong><br>
        Kérjük, győződjön meg arról, hogy a MySQL fut, és hogy a <code>config.ini</code> tartalmazza a helyes gazdagépet, portot, felhasználót és jelszót, majd
        <a href="<?= BASE_URL ?>/setup.php">frissítse ezt az oldalt</a>.
      </p>
      <div class="card" style="margin-top:16px;">
        <div class="card-body">
          <strong>config.ini helye:</strong><br>
          <code><?= e(__DIR__ . '/config.ini') ?></code>
          <hr style="margin:12px 0; border:none; border-top:1px solid var(--border);">
          <strong>Jelenlegi beállítások:</strong><br>
          Gazdagép: <code><?= e(DB_HOST) ?>:<?= e(DB_PORT) ?></code><br>
          Felhasználó: <code><?= e(DB_USER) ?></code><br>
          Adatbázis: <code><?= e(DB_NAME) ?></code>
        </div>
      </div>
    <?php else: ?>
      <p class="setup-desc">
        A(z) <strong><?= e(DB_NAME) ?></strong> adatbázis nem található. Ez a varázsló létrehozza azt, és beállítja az első admin fiókot.
      </p>

      <ul class="step-list">
        <li>A(z) <strong><?= e(DB_NAME) ?></strong> adatbázis létrehozása</li>
        <li>A <strong>users</strong> tábla létrehozása az összes tagmezővel</li>
        <li>Admin fiók létrehozása</li>
      </ul>

      <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="post">
        <div class="form-grid" style="margin-top:20px;">
          <div class="form-group">
            <label>Keresztnév</label>
            <input type="text" name="admin_firstname" value="<?= e($_POST['admin_firstname'] ?? '') ?>" required autofocus>
          </div>
          <div class="form-group">
            <label>Vezetéknév</label>
            <input type="text" name="admin_lastname" value="<?= e($_POST['admin_lastname'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Felhasználónév</label>
            <input type="text" name="admin_username" value="<?= e($_POST['admin_username'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>E-mail</label>
            <input type="email" name="admin_email" value="<?= e($_POST['admin_email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label>Jelszó</label>
            <input type="password" name="admin_password" required minlength="6">
          </div>
          <div class="form-group">
            <label>Jelszó megerősítése</label>
            <input type="password" name="admin_password2" required>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:20px;">
          Adatbázis &amp; admin fiók létrehozása
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
