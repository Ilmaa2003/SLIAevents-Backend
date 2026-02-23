<?php
// File: SendFellowshipPassEmail.php

namespace App\Jobs;

use App\Models\FellowshipRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class SendFellowshipPassEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];
    public $timeout = 60;

    protected $registrationData;
    protected $qrCode;
    protected $registrationId;

    /**
     * Create a new job instance.
     */
    public function __construct($registrationData, $qrCode, $registrationId)
    {
        $this->registrationData = $registrationData;
        $this->qrCode = $qrCode;
        $this->registrationId = $registrationId;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $registration = FellowshipRegistration::find($this->registrationId);
            if (!$registration) {
                Log::error('Fellowship registration not found in job: ' . $this->registrationId);
                return;
            }

            // Generate PDF
            $pdf = Pdf::loadView('pdf.fellowship-pass', [
                'registration' => $registration,
                'qrCode' => $this->qrCode,
                'name' => $this->registrationData['full_name'],
                'membership' => $this->registrationData['membership_number'],
            ]);

            $pdfContent = $pdf->output();

            $identifier = $registration->membership_number 
                        ?? $registration->nic_passport 
                        ?? $registration->id;

            Mail::send('emails.fellowship-registration', [
                'registration' => $registration,
                'name' => $this->registrationData['full_name'],
                'membership' => $this->registrationData['membership_number'],
                'email' => $this->registrationData['email'],
                'qrCode' => $this->qrCode
            ], function ($message) use ($registration, $pdfContent, $identifier) {
                $message->to($this->registrationData['email'])
                        ->subject('Members Night 2026 - Registration Confirmation');
                
                $ccEmail = env('MAIL_ALWAYS_CC', 'sliaoffice2@gmail.com');
                $message->cc($ccEmail);

                $message->attachData($pdfContent, 
                            'Members-Night-Pass-' . $identifier . '.pdf',
                            ['mime' => 'application/pdf']
                        );

                $qrData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->qrCode));
                $message->attachData($qrData, 
                            'QR-Code-' . $identifier . '.png',
                            ['mime' => 'image/png']
                        );
            });

            Log::info('Fellowship pass email sent successfully', ['registration_id' => $this->registrationId]);

        } catch (\Exception $e) {
            Log::error('Fellowship pass email job failed: ' . $e->getMessage(), [
                'registration_id' => $this->registrationId,
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }
}
