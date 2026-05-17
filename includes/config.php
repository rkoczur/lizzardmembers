<?php
$_cfgFile = dirname(__DIR__) . '/config.ini';
if (!file_exists($_cfgFile)) {
    die('config.ini not found. Please create it in the application root.');
}
$_cfg = parse_ini_file($_cfgFile, true);

define('DB_HOST',    $_cfg['database']['host']     ?? 'localhost');
define('DB_PORT',    $_cfg['database']['port']     ?? '3306');
define('DB_USER',    $_cfg['database']['user']     ?? 'root');
define('DB_PASS',    $_cfg['database']['password'] ?? '');
define('DB_NAME',    $_cfg['database']['dbname']   ?? '');
define('BASE_URL',   rtrim($_cfg['app']['base_url']  ?? '', '/'));
define('APP_NAME',   $_cfg['app']['app_name']         ?? 'LizzardMembers');
define('AVATAR_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars' . DIRECTORY_SEPARATOR);
define('AVATAR_URL', BASE_URL . '/assets/uploads/avatars/');
define('FLAG_DIR',   dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'flags' . DIRECTORY_SEPARATOR);
define('FLAG_URL',   BASE_URL . '/assets/uploads/flags/');
define('GPX_DIR',    dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'gpx' . DIRECTORY_SEPARATOR);
define('GPX_URL',    BASE_URL . '/assets/uploads/gpx/');
