<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env.local')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');
} else {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
}
$dotenv->safeLoad();

define('DB_NAME', $_ENV['DB_NAME'] ?? '');
define('DB_USER', $_ENV['DB_USER'] ?? '');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? '');
define('DB_HOST', $_ENV['DB_HOST'] ?? '');
define('DB_PORT', $_ENV['DB_PORT'] ?? '');
const TB_PREF = '';
const DB_CHARSET = 'utf8';
