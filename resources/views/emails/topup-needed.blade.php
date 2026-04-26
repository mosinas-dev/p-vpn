@php
    $shortfall = number_format($shortfallKopecks / 100, 2, '.', ' ');
@endphp
<!DOCTYPE html>
<html lang="ru">
<body style="font-family: -apple-system, system-ui, sans-serif; color: #1f2937; line-height: 1.5;">
<h2 style="color: #b45309;">Подписка истекает через {{ $daysLeft }} дн.</h2>

<p>На балансе кошелька не хватает средств для автоматического продления подписки на {{ $subscription->months }} мес.</p>

<p>Нужно пополнить минимум на <strong>{{ $shortfall }} ₽</strong>.</p>

<p style="margin: 24px 0;">
    <a href="{{ $payUrl }}"
       style="display: inline-block; background: #4f46e5; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none;">
        Пополнить и продлить
    </a>
</p>

<p style="color: #6b7280; font-size: 13px;">
    Изменить срок продления и управлять автосписанием — в <a href="{{ url('/wallet') }}">личном кабинете</a>.
</p>
</body>
</html>
