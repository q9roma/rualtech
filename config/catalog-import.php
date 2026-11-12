<?php
return [
    'python' => env('CATALOG_PYTHON', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
    // Opt in per deployment; production remains disabled by default.
    'photos_enabled' => env('CATALOG_PHOTOS_ENABLED', false),
    'rules' => 'b2b-2026-11-08-v1',
];
