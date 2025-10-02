<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

echo "=== Тест авторизации ===\n";

$email = 'admin@admin.com';
$password = '123456';

echo "Попытка входа с: $email / $password\n";

// Найдем пользователя
$user = User::where('email', $email)->first();

if (!$user) {
    echo "❌ Пользователь не найден\n";
    exit;
}

echo "✅ Пользователь найден: {$user->name} (ID: {$user->id})\n";

// Проверим пароль
if (Hash::check($password, $user->password)) {
    echo "✅ Пароль верный\n";
} else {
    echo "❌ Пароль неверный\n";
    echo "Хеш в БД: {$user->password}\n";
    echo "Проверяемый хеш: " . Hash::make($password) . "\n";
}

// Попробуем аутентификацию
if (Auth::attempt(['email' => $email, 'password' => $password])) {
    echo "✅ Auth::attempt успешно\n";
    echo "Аутентифицированный пользователь: " . Auth::user()->name . "\n";
} else {
    echo "❌ Auth::attempt неудачно\n";
}