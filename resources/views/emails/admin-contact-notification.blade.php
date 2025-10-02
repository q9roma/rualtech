<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Новая заявка с сайта</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2563eb; color: white; padding: 20px; text-align: center; }
        .content { background: #f8fafc; padding: 20px; }
        .field { margin-bottom: 15px; }
        .label { font-weight: bold; color: #4a5568; }
        .value { background: white; padding: 10px; border-radius: 4px; margin-top: 5px; }
        .footer { text-align: center; padding: 20px; color: #718096; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Новая заявка с сайта</h1>
        </div>
        
        <div class="content">
            <p><strong>Получена новая заявка с сайта {{ config('app.name') }}</strong></p>
            
            <div class="field">
                <div class="label">Имя:</div>
                <div class="value">{{ $contactRequest->name }}</div>
            </div>
            
            <div class="field">
                <div class="label">Email:</div>
                <div class="value">{{ $contactRequest->email }}</div>
            </div>
            
            @if($contactRequest->phone)
            <div class="field">
                <div class="label">Телефон:</div>
                <div class="value">{{ $contactRequest->phone }}</div>
            </div>
            @endif
            
            @if($contactRequest->company)
            <div class="field">
                <div class="label">Компания:</div>
                <div class="value">{{ $contactRequest->company }}</div>
            </div>
            @endif
            
            <div class="field">
                <div class="label">Тема:</div>
                <div class="value">{{ $contactRequest->subject }}</div>
            </div>
            
            @if($contactRequest->service)
            <div class="field">
                <div class="label">Интересующий продукт/услуга:</div>
                <div class="value">{{ $contactRequest->service }}</div>
            </div>
            @endif
            
            <div class="field">
                <div class="label">Сообщение:</div>
                <div class="value">{{ $contactRequest->message }}</div>
            </div>
            
            <div class="field">
                <div class="label">Источник:</div>
                <div class="value">{{ $contactRequest->source_label }}</div>
            </div>
            
            <div class="field">
                <div class="label">Дата подачи:</div>
                <div class="value">{{ $contactRequest->created_at->format('d.m.Y H:i') }}</div>
            </div>
        </div>
        
        <div class="footer">
            <p>Это автоматическое уведомление с сайта {{ config('app.name') }}</p>
            <p>ID заявки: #{{ $contactRequest->id }}</p>
        </div>
    </div>
</body>
</html>