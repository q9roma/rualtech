<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Создаем простого админа
$user = User::create([
    'name' => 'Simple Admin',
    'email' => 'admin@test.com',
    'password' => Hash::make('password'),
    'email_verified_at' => now(),
]);

echo "Создан новый пользователь:\n";
echo "Email: admin@test.com\n";
echo "Password: password\n";
echo "ID: " . $user->id . "\n";