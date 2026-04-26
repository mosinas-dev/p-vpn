<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionAutoRenewed extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Subscription $subscription, public int $remainingBalanceKopecks)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Подписка продлена с баланса');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription-auto-renewed',
            with: [
                'subscription' => $this->subscription,
                'remainingBalanceKopecks' => $this->remainingBalanceKopecks,
            ],
        );
    }
}
