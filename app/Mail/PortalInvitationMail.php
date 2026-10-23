<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $acceptUrl,
        public string $recipientName,
        public string $roleLabel,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You are invited to the Royal Indian Stones staff portal',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.portal-invitation',
        );
    }
}
