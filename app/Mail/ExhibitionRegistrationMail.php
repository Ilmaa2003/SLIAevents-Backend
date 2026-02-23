<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;

class ExhibitionRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct($data, $pdfContent)
    {
        $this->data = $data;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: 'SLIA Exhibition - Your Entry Pass & Registration Confirmation',
        );

        if ($ccEmail = env('MAIL_ALWAYS_CC')) {
            $envelope->cc = [$ccEmail];
        }

        return $envelope;
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.exhibition-pass',
            with: [
                'name' => $this->data['full_name'],
                'membership' => $this->data['membership_number'],
                'email' => $this->data['email'],
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'event_name' => 'SLIA Annual Exhibition 2026'
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $safeMembership = str_replace(['/', '\\'], '-', $this->data['membership_number']);
        
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'SLIA-Exhibition-Pass-' . $safeMembership . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
