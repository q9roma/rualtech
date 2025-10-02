<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Подтверждение получения заявки</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #059669; color: white; padding: 20px; text-align: center; }
        .content { background: #f0fdf4; padding: 20px; }
        .highlight { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid #059669; }
        .footer { text-align: center; padding: 20px; color: #6b7280; font-size: 14px; }
        .contact-info { background: white; padding: 15px; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>✅ Заявка получена!</h1>
        </div>
        
        <div class="content">
            <p><strong>Здравствуйте, {{ $contactRequest->name }}!</strong></p>
            
            <p>Спасибо за обращение в компанию <strong>{{ config('app.name') }}</strong>. Ваша заявка успешно получена и обрабатывается.</p>
            
            <div class="highlight">
                <h3>📋 Детали вашей заявки:</h3>
                <p><strong>Тема:</strong> {{ $contactRequest->subject }}</p>
                @if($contactRequest->service)
                <p><strong>Интересующий продукт:</strong> {{ $contactRequest->service }}</p>
                @endif
                <p><strong>Номер заявки:</strong> #{{ $contactRequest->id }}</p>
                <p><strong>Дата подачи:</strong> {{ $contactRequest->created_at->format('d.m.Y в H:i') }}</p>
            </div>
            
            <p><strong>Что происходит дальше?</strong></p>
            <ul>
                <li>✓ Ваша заявка передана нашим специалистам</li>
                <li>📞 Мы свяжемся с вами в течение 2-4 часов в рабочее время</li>
                <li>💼 Подготовим персональное предложение</li>
                <li>🎯 Поможем подобрать оптимальное решение</li>
            </ul>
            
            <div class="contact-info">
                <h3>📞 Наши контакты:</h3>
                <p><strong>Телефон:</strong> +7 (XXX) XXX-XX-XX</p>
                <p><strong>Email:</strong> office@rualtech.ru</p>
                <p><strong>Сайт:</strong> {{ config('app.url') }}</p>
                <p><strong>Время работы:</strong> Пн-Пт с 9:00 до 18:00</p>
            </div>
            
            <p>Если у вас есть срочные вопросы, вы можете связаться с нами по указанным выше контактам.</p>
        </div>
        
        <div class="footer">
            <p>С уважением,<br>
            Команда {{ config('app.name') }}</p>
            <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 20px 0;">
            <p style="font-size: 12px; color: #9ca3af;">
                Это автоматическое письмо-подтверждение. Пожалуйста, не отвечайте на него.
            </p>
        </div>
    </div>
</body>
</html>