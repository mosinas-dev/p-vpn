@php
    $price = number_format($subscription->price_kopecks / 100, 0, '.', ' ');
    $balance = number_format($remainingBalanceKopecks / 100, 2, '.', ' ');
@endphp
<!DOCTYPE html>
<html lang="ru">
<body style="font-family: -apple-system, system-ui, sans-serif; color: #1f2937; line-height: 1.5;">
<h2 style="color: #047857;">Подписка автоматически продлена</h2>

<p>Здравствуйте!</p>

<p>С вашего кошелька списано <strong>{{ $price }} ₽</strong> за продление подписки на {{ $subscription->months }} мес.</p>

<p>Подписка действует до <strong>{{ $subscription->ends_at?->format('d.m.Y') }}</strong>.</p>

<p>Остаток на балансе: <strong>{{ $balance }} ₽</strong>.</p>

<p style="color: #6b7280; font-size: 13px;">
    Чтобы изменить настройки автопродления, зайдите в <a href="{{ url('/wallet') }}">кошелёк</a>.
</p>
</body>
</html>
