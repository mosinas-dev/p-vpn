<?php

namespace App\Mail;

use App\Models\VpnKey;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KeyIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VpnKey $key)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ваш VPN-ключ готов');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.key-issued');
    }
}
