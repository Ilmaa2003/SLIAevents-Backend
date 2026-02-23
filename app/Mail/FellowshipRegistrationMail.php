<?php
// File: FellowshipRegistrationMail.php

namespace App\Mail;

use App\Models\FellowshipRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FellowshipRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $registration;
    public $pdfContent;
    public $qrCode;
    public $name;
    public $membership;
    public $email;

    /**
     * Create a new message instance.
     */
    public function __construct(FellowshipRegistration $registration, $pdfContent, $qrCode, $name, $membership, $email)
    {
        $this->registration = $registration;
        $this->pdfContent = $pdfContent;
        $this->qrCode = $qrCode;
        $this->name = $name;
        $this->membership = $membership;
        $this->email = $email;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            subject: 'Members Night 2026 - Registration Confirmation',
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
            view: 'emails.fellowship-registration',
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
                    ?? $this->registration->nic_passport 
                    ?? $this->registration->id;

        $attachments = [
            Attachment::fromData(fn () => $this->pdfContent, 'Fellowship-Pass-' . $identifier . '.pdf')
                ->withMime('application/pdf'),
        ];

        if ($this->qrCode) {
            $qrData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->qrCode));
            $attachments[] = Attachment::fromData(fn () => $qrData, 'QR-Code-' . $identifier . '.png')
                ->withMime('image/png');
        }

        return $attachments;
    }
}
