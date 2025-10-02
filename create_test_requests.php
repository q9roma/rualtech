<?php

require_once 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ContactRequest;

// Создаем несколько тестовых заявок
$requests = [
    [
        'name' => 'Иван Петров',
        'email' => 'ivan@example.com',
        'phone' => '+7 (495) 123-45-67',
        'company' => 'ООО Тест',
        'subject' => 'Консультация по IT-решениям',
        'message' => 'Интересует внедрение CRM системы для нашей компании',
        'service' => 'IT-консалтинг',
        'source' => 'website',
        'status' => 'new'
    ],
    [
        'name' => 'Анна Сидорова',
        'email' => 'anna@company.ru',
        'phone' => '+7 (495) 987-65-43',
        'company' => 'ТехноСфера',
        'subject' => 'Заказ оборудования',
        'message' => 'Нужно 10 компьютеров для офиса',
        'service' => 'Поставка оборудования',
        'source' => 'service_page',
        'status' => 'new'
    ],
    [
        'name' => 'Дмитрий Козлов',
        'email' => 'dmitry@business.com',
        'phone' => '+7 (495) 555-33-22',
        'company' => 'БизнесПро',
        'subject' => 'Техническая поддержка',
        'message' => 'Требуется настройка сервера',
        'service' => 'Техподдержка',
        'source' => 'contact_form',
        'status' => 'in_progress'
    ]
];

foreach ($requests as $requestData) {
    ContactRequest::create($requestData);
    echo "✅ Создана заявка от {$requestData['name']}\n";
}

echo "\nВсего заявок в базе: " . ContactRequest::count() . "\n";
echo "Новых заявок: " . ContactRequest::where('status', 'new')->count() . "\n";