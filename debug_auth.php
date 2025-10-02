<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo "=== Детальная диагностика авторизации ===\n";

$email = 'admin@admin.com';
$password = '123456';

// 1. Проверим подключение к БД
try {
    $userCount = User::count();
    echo "✅ Подключение к БД работает. Пользователей: $userCount\n";
} catch (Exception $e) {
    echo "❌ Ошибка подключения к БД: " . $e->getMessage() . "\n";
    exit;
}

// 2. Найдем пользователя
$user = User::where('email', $email)->first();
if (!$user) {
    echo "❌ Пользователь с email '$email' не найден\n";
    
    // Покажем всех пользователей
    echo "Список всех пользователей:\n";
    User::all()->each(function($u) {
        echo "- ID: {$u->id}, Email: {$u->email}, Name: {$u->name}\n";
    });
    exit;
}

echo "✅ Пользователь найден:\n";
echo "  ID: {$user->id}\n";
echo "  Email: {$user->email}\n";
echo "  Name: {$user->name}\n";
echo "  Password hash: " . substr($user->password, 0, 30) . "...\n";
echo "  Email verified: " . ($user->email_verified_at ? 'Да' : 'Нет') . "\n";

// 3. Проверим хеширование
echo "\n--- Тестирование хеширования ---\n";
$testHash = Hash::make($password);
echo "Новый хеш для '$password': " . substr($testHash, 0, 30) . "...\n";

// 4. Проверим старый пароль
if (Hash::check($password, $user->password)) {
    echo "✅ Hash::check() - пароль ВЕРНЫЙ\n";
} else {
    echo "❌ Hash::check() - пароль НЕВЕРНЫЙ\n";
    
    // Попробуем другие варианты пароля
    $variants = ['admin123', 'password', '123456', 'admin'];
    echo "Проверяем другие варианты:\n";
    foreach ($variants as $variant) {
        if (Hash::check($variant, $user->password)) {
            echo "✅ Найден правильный пароль: '$variant'\n";
            break;
        } else {
            echo "❌ '$variant' - неверно\n";
        }
    }
}

// 5. Обновим пароль принудительно
echo "\n--- Принудительное обновление пароля ---\n";
$user->password = Hash::make($password);
$user->save();
echo "✅ Пароль обновлен\n";

// 6. Проверим еще раз
if (Hash::check($password, $user->fresh()->password)) {
    echo "✅ После обновления пароль проверяется корректно\n";
} else {
    echo "❌ Даже после обновления пароль не проверяется\n";
}