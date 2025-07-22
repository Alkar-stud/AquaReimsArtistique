<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Charge .env s'il existe
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// Charge .env.local s'il existe (prioritaire)
$dotenvLocal = Dotenv\Dotenv::createImmutable(__DIR__ . '/../', '.env.local');
$dotenvLocal->safeLoad();

define("SMTP_USER", $_ENV['SMTP_USER'] ?? '');
define("SMTP_USERNAME", $_ENV['SMTP_USERNAME'] ?? '');
define("SMTP_PASSWORD", $_ENV['SMTP_PASSWORD'] ?? '');
define("SMTP_HOST", $_ENV['SMTP_HOST'] ?? '');
define("SMTP_SECURE", $_ENV['SMTP_SECURE'] ?? '');
define("SMTP_PORT", $_ENV['SMTP_PORT'] ?? '');
