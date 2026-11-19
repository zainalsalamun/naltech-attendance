<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalStatusNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $recipientName,
        public readonly string $notificationTitle,
        public readonly string $summaryLine,
        public readonly string $kindLabel,
        public readonly string $applicantName,
        public readonly string $statusLabel,
        public readonly ?string $reasonText = null,
        public readonly ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Naltech HRIS] '.$this->notificationTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.approval-status-notification',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
