<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ManualEntryNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $eventType;
    public $membershipNumber;
    public $fullName;
    public $email;
    public $mobile;
    public $mealPreference;

    /**
     * Create a new message instance.
     */
    public function __construct($eventType, $membershipNumber, $fullName, $email, $mobile, $mealPreference = null)
    {
        $this->eventType = $eventType;
        $this->membershipNumber = $membershipNumber;
        $this->fullName = $fullName;
        $this->email = $email;
        $this->mobile = $mobile;
        $this->mealPreference = $mealPreference;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Manual Entry Alert - {$this->eventType} Registration",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.manual-entry-notification',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
