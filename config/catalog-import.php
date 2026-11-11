<?php
return [
    'python' => env('CATALOG_PYTHON', PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'),
    'rules' => 'b2b-2026-11-08-v1',
];
