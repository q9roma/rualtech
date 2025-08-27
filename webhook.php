<?php
/**
 * Простой webhook для автоматического обновления сайта из GitHub
 * Разместите этот файл как webhook.php в корне сайта
 */

// Секретный токен для безопасности (замените на свой)
$secret = 'ваш_секретный_токен_здесь';

// Получаем данные от GitHub
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Проверяем подпись (безопасность)
if (!hash_equals('sha256=' . hash_hmac('sha256', $payload, $secret), $signature)) {
    http_response_code(403);
    die('Unauthorized');
}

// Логируем запрос
file_put_contents('webhook.log', date('Y-m-d H:i:s') . " - Webhook triggered\n", FILE_APPEND);

// Выполняем обновление
$output = [];
$return_var = 0;

// Переходим в директорию проекта
chdir('/var/www/www-root/data/www/rualtech.ru');

// Выполняем git pull
exec('git pull origin main 2>&1', $output, $return_var);

if ($return_var === 0) {
    // Если git pull успешен, выполняем дополнительные команды
    exec('composer install --no-dev --optimize-autoloader 2>&1', $output, $return_var);
    exec('npm install --production 2>&1', $output, $return_var);
    exec('npm run build 2>&1', $output, $return_var);
    exec('php artisan config:clear 2>&1', $output, $return_var);
    exec('php artisan cache:clear 2>&1', $output, $return_var);
    exec('php artisan config:cache 2>&1', $output, $return_var);
    
    $message = "Deployment successful";
    $status = 200;
} else {
    $message = "Deployment failed";
    $status = 500;
}

// Логируем результат
file_put_contents('webhook.log', date('Y-m-d H:i:s') . " - $message\n" . implode("\n", $output) . "\n\n", FILE_APPEND);

http_response_code($status);
echo $message;
?>
