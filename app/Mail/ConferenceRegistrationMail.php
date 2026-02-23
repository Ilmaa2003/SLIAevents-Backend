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
    public $qrCode;

    /**
     * Create a new message instance.
     */
    public function __construct(ConferenceRegistration $registration, $pdfContent, $qrCode = null)
    {
        $this->registration = $registration;
        $this->pdfContent = $pdfContent;
        $this->qrCode = $qrCode;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: 'SLIA Conference 2026 - Registration Confirmation',
        );

        if ($ccEmail = env('MAIL_ALWAYS_CC', 'sliaanualevents@gmail.com')) {
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
        $identifier = $this->registration->membership_number 
                    ?? $this->registration->student_id 
                    ?? $this->registration->nic_passport 
                    ?? $this->registration->id;

        $attachments = [
            Attachment::fromData(fn () => $this->pdfContent, 'Conference-Pass-' . $identifier . '.pdf')
                ->withMime('application/pdf'),
        ];

        if ($this->qrCode) {
            // Extract raw data from Data URI
            $qrData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->qrCode));
            $attachments[] = Attachment::fromData(fn () => $qrData, 'QR-Code-' . $identifier . '.png')
                ->withMime('image/png');
        }

        return $attachments;
    }
}