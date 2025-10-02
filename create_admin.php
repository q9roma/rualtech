<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';

// Загружаем провайдеры
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Удаляем всех пользователей
User::truncate();

// Создаем нового админа
$user = User::create([
    'name' => 'Admin',
    'email' => 'admin@admin.com',
    'password' => Hash::make('123456'),
]);

echo "Пользователь создан:\n";
echo "Email: admin@admin.com\n";
echo "Password: 123456\n";
echo "ID: " . $user->id . "\n";