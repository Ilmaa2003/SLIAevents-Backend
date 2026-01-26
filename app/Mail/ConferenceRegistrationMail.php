<?php

namespace App\Mail;

use App\Models\ConferenceRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ConferenceRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $registration;
    public $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct(ConferenceRegistration $registration, $pdfContent)
    {
        $this->registration = $registration;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'SLIA Conference 2026 - Registration Confirmation',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.conference-registration',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'Conference-Pass-' . $this->registration->membership_number . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}