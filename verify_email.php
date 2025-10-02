<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

$user = User::find(1);
$user->email_verified_at = now();
$user->save();

echo "Email верифицирован для пользователя: " . $user->email;