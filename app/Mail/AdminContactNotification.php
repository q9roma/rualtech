<?php

namespace App\Mail;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminContactNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContactRequest $contactRequest
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Новая заявка с сайта: ' . $this->contactRequest->subject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-contact-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
