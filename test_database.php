<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "=== Диагностика базы данных ===\n";

try {
    // Проверяем подключение
    $pdo = DB::connection()->getPdo();
    echo "✅ MySQL подключение работает\n";
    
    // Проверяем базу данных
    $database = DB::connection()->getDatabaseName();
    echo "📊 База данных: $database\n";
    
    // Проверяем таблицы
    $tables = DB::select('SHOW TABLES');
    echo "📋 Количество таблиц: " . count($tables) . "\n";
    
    // Проверяем пользователей
    $userCount = DB::table('users')->count();
    echo "👥 Пользователей в БД: $userCount\n";
    
    // Проверяем заявки
    $requestCount = DB::table('contact_requests')->count();
    echo "📝 Заявок в БД: $requestCount\n";
    
} catch (Exception $e) {
    echo "❌ Ошибка подключения к БД: " . $e->getMessage() . "\n";
    echo "📝 Проверьте:\n";
    echo "   1. Запущен ли MySQL в XAMPP\n";
    echo "   2. Существует ли база данных 'altech_local'\n";
    echo "   3. Правильные ли настройки в .env\n";
}