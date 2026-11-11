<?php

// Parse dotenv rather than relying on Windows findstr line-ending behavior.
$root = dirname(__DIR__);
if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Dependencies missing. Run composer install.\n");
    exit(1);
}
require $root.'/vendor/autoload.php';
if (! is_file($root.'/.env.local')) {
    fwrite(STDERR, "Missing .env.local. See docs/local-development.md.\n");
    exit(1);
}
try {
    $settings = Dotenv\Dotenv::parse(file_get_contents($root.'/.env.local'));
} catch (Throwable) {
    // Parsing errors can contain config values; do not expose them.
    fwrite(STDERR, "Invalid .env.local format.\n");
    exit(1);
}
if (($settings['APP_ENV'] ?? null) !== 'local') {
    fwrite(STDERR, ".env.local must set APP_ENV=local.\n");
    exit(1);
}
echo "Local environment verified.\n";
