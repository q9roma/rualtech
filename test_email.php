<?php
/**
 * Скрипт для тестирования отправки email на продакшн сервере
 * Запуск: php test_email.php
 */

require_once 'vendor/autoload.php';

// Загружаем Laravel
$app = require_once 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use App\Models\ContactRequest;
use App\Mail\AdminContactNotification;

echo "🔍 Диагностика почтовых настроек\n";
echo "================================\n\n";

// 1. Проверяем настройки почты
echo "📧 Настройки почты:\n";
echo "MAIL_MAILER: " . config('mail.default') . "\n";
echo "MAIL_HOST: " . config('mail.mailers.smtp.host') . "\n";
echo "MAIL_PORT: " . config('mail.mailers.smtp.port') . "\n";
echo "MAIL_USERNAME: " . config('mail.mailers.smtp.username') . "\n";
echo "MAIL_FROM_ADDRESS: " . config('mail.from.address') . "\n";
echo "MAIL_FROM_NAME: " . config('mail.from.name') . "\n";
echo "Admin Email: " . config('mail.admin_email', 'office@rualtech.ru') . "\n\n";

// 2. Проверяем переменные окружения
echo "🔧 Переменные окружения:\n";
$envVars = ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
foreach ($envVars as $var) {
    $value = env($var);
    echo "$var: " . ($value ? (strpos($var, 'PASSWORD') !== false ? '***скрыто***' : $value) : 'НЕ УСТАНОВЛЕНО') . "\n";
}
echo "\n";

// 3. Создаем тестовую заявку
echo "📝 Создаем тестовую заявку...\n";
$testRequest = new ContactRequest([
    'name' => 'Тест системы',
    'email' => 'test@example.com',
    'phone' => '+7 (999) 123-45-67',
    'company' => 'Тестовая компания',
    'subject' => 'Тест отправки email',
    'message' => 'Это тестовое сообщение для проверки работы email системы.',
    'service' => 'Тестовая услуга',
    'source' => 'test',
    'meta_data' => [
        'test' => true,
        'timestamp' => now()->toISOString()
    ]
]);

// 4. Пытаемся отправить email
echo "📤 Отправляем тестовый email...\n";
try {
    $adminEmail = config('mail.admin_email', 'office@rualtech.ru');
    
    Mail::to($adminEmail)->send(new AdminContactNotification($testRequest));
    
    echo "✅ Email успешно отправлен на: $adminEmail\n";
    
} catch (\Exception $e) {
    echo "❌ Ошибка отправки email:\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
}

// 5. Проверяем логи
echo "\n📋 Последние записи в логах:\n";
$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logs = file($logFile);
    $recentLogs = array_slice($logs, -10);
    foreach ($recentLogs as $log) {
        if (strpos($log, 'mail') !== false || strpos($log, 'email') !== false || strpos($log, 'заявка') !== false) {
            echo trim($log) . "\n";
        }
    }
} else {
    echo "Файл логов не найден: $logFile\n";
}

// 6. Проверяем последние заявки из БД
echo "\n📊 Последние 5 заявок из базы данных:\n";
try {
    $recentRequests = ContactRequest::orderBy('created_at', 'desc')->take(5)->get();
    
    if ($recentRequests->count() > 0) {
        foreach ($recentRequests as $request) {
            echo "ID: {$request->id} | {$request->created_at} | {$request->email} | {$request->subject}\n";
        }
    } else {
        echo "Заявок в базе данных не найдено\n";
    }
} catch (\Exception $e) {
    echo "❌ Ошибка при получении заявок: " . $e->getMessage() . "\n";
}

echo "\n🏁 Диагностика завершена!\n";
