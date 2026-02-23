<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\ExhibitionRegistrationMail;

class SendExhibitionPassEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];
    public $timeout = 60;

    protected $registrationData;
    protected $qrCode;
    protected $registrationId;

    public function __construct($registrationData, $qrCode, $registrationId)
    {
        $this->registrationData = $registrationData;
        $this->qrCode = $qrCode;
        $this->registrationId = $registrationId;
    }

    public function handle()
    {
        try {
            // Generate PDF
            $pdf = Pdf::loadView('pdf.exhibition-pass', [
                'membership' => $this->registrationData['membership_number'],
                'name' => $this->registrationData['full_name'],
                'email' => $this->registrationData['email'],
                'mobile' => $this->registrationData['mobile'],
                'qr' => $this->qrCode,
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'registration_id' => $this->registrationId,
                'pass_type' => 'Exhibition Entry Pass',
                'event_name' => 'SLIA Annual Exhibition 2026',
                'attended' => false,
                'meal_received' => false
            ]);

            $pdfContent = $pdf->output();

            // Send email using Mailable
            // Send email using closure (matching Inauguration pattern)
            Mail::send('emails.exhibition-pass', [
                'name' => $this->registrationData['full_name'],
                'membership' => $this->registrationData['membership_number'],
                'email' => $this->registrationData['email'],
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'event_name' => 'SLIA Annual Exhibition 2026'
            ], function ($message) use ($pdfContent) {
                $message->to($this->registrationData['email'])
                        ->subject('SLIA Exhibition - Your Entry Pass & Registration Confirmation');
                
                if ($ccEmail = env('MAIL_ALWAYS_CC')) {
                    $message->cc($ccEmail);
                }

                $safeMembership = str_replace(['/', '\\'], '-', $this->registrationData['membership_number']);
                
                $message->attachData($pdfContent, 
                            'SLIA-Exhibition-Pass-' . $safeMembership . '.pdf',
                            ['mime' => 'application/pdf']
                        );
            });

            Log::info('Exhibition Queued email sent successfully', ['registration_id' => $this->registrationId]);

        } catch (\Exception $e) {
            Log::error('Exhibition Queue job failed: ' . $e->getMessage(), [
                'registration_id' => $this->registrationId,
                'attempt' => $this->attempts()
            ]);
            throw $e;
        }
    }
}
