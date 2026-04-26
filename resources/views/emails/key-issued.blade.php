<!DOCTYPE html>
<html lang="ru">
<body style="font-family: -apple-system, system-ui, sans-serif; color: #1f2937; line-height: 1.5;">
<h2>Ваш VPN-ключ готов</h2>

<p>Подписка активна. Ключ <strong>{{ $key->name }}</strong> выпущен на сервере #{{ $key->panel_server_id }}.</p>

<p>Скачайте конфиг или отсканируйте QR-код в приложении Amnezia VPN. Они доступны в <a href="{{ url('/dashboard') }}">личном кабинете</a>.</p>
</body>
</html>
