<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TopupNeeded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public int $daysLeft,
        public int $shortfallKopecks,
        public string $payUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->daysLeft > 0
                ? "Подписка истекает через {$this->daysLeft} дн., пополните кошелёк"
                : 'Подписка истекла — пополните, чтобы продлить',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.topup-needed');
    }
}
