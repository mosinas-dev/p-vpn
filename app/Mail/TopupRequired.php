<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TopupRequired extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $expiredSubscription,
        public int $shortfallKopecks,
        public string $payUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Подписка истекла — пополните, чтобы продлить');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.topup-required');
    }
}
