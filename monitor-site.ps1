# Мониторинг статуса сайта rualtech.ru

$url = "https://rualtech.ru"
$maxAttempts = 20
$delay = 30  # секунд между проверками

Write-Host "Начинаем мониторинг сайта $url"
Write-Host "Максимум попыток: $maxAttempts, интервал: $delay секунд"
Write-Host "$(Get-Date) - Ожидаем восстановления..."

for ($i = 1; $i -le $maxAttempts; $i++) {
    try {
        # Игнорируем SSL ошибки для PowerShell 5
        [System.Net.ServicePointManager]::ServerCertificateValidationCallback = {$true}
        $response = Invoke-WebRequest -Uri $url -Method Head -TimeoutSec 10
        if ($response.StatusCode -eq 200) {
            Write-Host "$(Get-Date) - ✅ САЙТ ВОССТАНОВЛЕН! Статус: $($response.StatusCode)" -ForegroundColor Green
            break
        } else {
            Write-Host "$(Get-Date) - Попытка $i/$maxAttempts - Статус: $($response.StatusCode)" -ForegroundColor Yellow
        }
    } catch {
        Write-Host "$(Get-Date) - Попытка $i/$maxAttempts - Ошибка: $($_.Exception.Message)" -ForegroundColor Red
    }
    
    if ($i -lt $maxAttempts) {
        Start-Sleep -Seconds $delay
    }
}

if ($i -gt $maxAttempts) {
    Write-Host "$(Get-Date) - ❌ Сайт не восстановился после $maxAttempts попыток" -ForegroundColor Red
    Write-Host "Проверьте GitHub Actions: https://github.com/q9roma/rualtech/actions"
}
