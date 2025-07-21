<?php

$config = [];

$envFile = __DIR__ . '/../.env.local';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (preg_match('/^\s*#/', $line) || trim($line) === '') continue;
        if (preg_match('/^(DB_|MAIL_)/', $line)) continue;
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $matches)) {
            $key = $matches[1];
            $value = trim($matches[2], "\"'");
            $config[strtolower($key)] = $value;
        }
    }
}