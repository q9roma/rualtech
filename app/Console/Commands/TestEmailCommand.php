<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\ContactRequest;
use App\Mail\AdminContactNotification;

class TestEmailCommand extends Command
{
    protected $signature = 'email:test {--email=office@rualtech.ru : Email для отправки тестового сообщения}';
    protected $description = 'Тестирует отправку email и показывает настройки почты';

    public function handle()
    {
        $this->info('🔍 Диагностика почтовых настроек');
        $this->info('================================');

        // 1. Показываем настройки почты
        $this->newLine();
        $this->info('📧 Настройки почты:');
        $this->table(['Параметр', 'Значение'], [
            ['MAIL_MAILER', config('mail.default')],
            ['MAIL_HOST', config('mail.mailers.smtp.host') ?: 'НЕ УСТАНОВЛЕНО'],
            ['MAIL_PORT', config('mail.mailers.smtp.port') ?: 'НЕ УСТАНОВЛЕНО'],
            ['MAIL_USERNAME', config('mail.mailers.smtp.username') ?: 'НЕ УСТАНОВЛЕНО'],
            ['MAIL_FROM_ADDRESS', config('mail.from.address') ?: 'НЕ УСТАНОВЛЕНО'],
            ['MAIL_FROM_NAME', config('mail.from.name') ?: 'НЕ УСТАНОВЛЕНО'],
            ['Admin Email', config('mail.admin_email', 'office@rualtech.ru')],
        ]);

        // 2. Проверяем переменные окружения
        $this->newLine();
        $this->info('🔧 Переменные окружения:');
        $envVars = ['MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS'];
        foreach ($envVars as $var) {
            $value = env($var);
            $displayValue = $value ? (strpos($var, 'PASSWORD') !== false ? '***скрыто***' : $value) : 'НЕ УСТАНОВЛЕНО';
            $this->line("$var: $displayValue");
        }

        // 3. Создаем тестовую заявку
        $this->newLine();
        $this->info('📝 Создаем тестовую заявку...');
        
        $testRequest = new ContactRequest([
            'name' => 'Тест системы',
            'email' => 'test@example.com',
            'phone' => '+7 (999) 123-45-67',
            'company' => 'Тестовая компания',
            'subject' => 'Тест отправки email - ' . now()->format('d.m.Y H:i:s'),
            'message' => 'Это тестовое сообщение для проверки работы email системы.',
            'service' => 'Тестовая услуга',
            'source' => 'test',
            'meta_data' => [
                'test' => true,
                'timestamp' => now()->toISOString()
            ]
        ]);

        // 4. Пытаемся отправить email
        $adminEmail = $this->option('email');
        $this->info("📤 Отправляем тестовый email на: $adminEmail");
        
        try {
            Mail::to($adminEmail)->send(new AdminContactNotification($testRequest));
            $this->info("✅ Email успешно отправлен!");
            
        } catch (\Exception $e) {
            $this->error("❌ Ошибка отправки email:");
            $this->error("Сообщение: " . $e->getMessage());
            $this->error("Файл: " . $e->getFile() . ":" . $e->getLine());
            
            if ($this->option('verbose')) {
                $this->error("Трассировка:");
                $this->error($e->getTraceAsString());
            }
        }

        // 5. Показываем последние заявки
        $this->newLine();
        $this->info('📊 Последние 5 заявок из базы данных:');
        
        try {
            $recentRequests = ContactRequest::orderBy('created_at', 'desc')->take(5)->get();
            
            if ($recentRequests->count() > 0) {
                $headers = ['ID', 'Дата', 'Email', 'Тема'];
                $rows = $recentRequests->map(function ($request) {
                    return [
                        $request->id,
                        $request->created_at->format('d.m.Y H:i:s'),
                        $request->email,
                        \Str::limit($request->subject, 50)
                    ];
                })->toArray();
                
                $this->table($headers, $rows);
            } else {
                $this->warn('Заявок в базе данных не найдено');
            }
        } catch (\Exception $e) {
            $this->error("❌ Ошибка при получении заявок: " . $e->getMessage());
        }

        // 6. Рекомендации
        $this->newLine();
        $this->info('💡 Рекомендации для диагностики:');
        $this->line('1. Проверьте файл .env на продакшн сервере');
        $this->line('2. Убедитесь, что MAIL_MAILER не равен "log"');
        $this->line('3. Проверьте логи: storage/logs/laravel.log');
        $this->line('4. Проверьте настройки SMTP сервера');
        $this->line('5. Убедитесь, что порт не заблокирован файрволом');

        return 0;
    }
}