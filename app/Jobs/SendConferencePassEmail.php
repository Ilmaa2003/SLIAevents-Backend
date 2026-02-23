<?php

namespace App\Jobs;

use App\Mail\ConferenceRegistrationMail;
use App\Models\ConferenceRegistration;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;

class SendConferencePassEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600];
    public $timeout = 60;
    public $failOnTimeout = false;

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
            // Find the registration model for the view
            $registration = ConferenceRegistration::find($this->registrationId);
            if (!$registration) {
                Log::error('Conference registration not found in job: ' . $this->registrationId);
                return;
            }

            // Generate PDF
            $pdf = Pdf::loadView('pdf.conference-pass', [
                'registration' => $registration,
                'qrCode' => $this->qrCode,
                'membership' => $this->registrationData['membership_number'],
                'name' => $this->registrationData['full_name'],
                'email' => $this->registrationData['email'],
                'phone' => $this->registrationData['mobile'],
                'category' => $registration->category,
                'date' => $registration->created_at->format('F j, Y'),
                'time' => $registration->created_at->format('h:i A'),
            ]);

            $pdfContent = $pdf->output();

            // Send email using closure style (Reverted to working pattern)
            $identifier = $registration->membership_number 
                        ?? $registration->student_id 
                        ?? $registration->nic_passport 
                        ?? $registration->id;

            Mail::send('emails.conference-registration', [
                'registration' => $registration,
                'name' => $this->registrationData['full_name'],
                'membership' => $this->registrationData['membership_number'],
                'email' => $this->registrationData['email'],
                'qrCode' => $this->qrCode
            ], function ($message) use ($registration, $pdfContent, $identifier) {
                $message->to($this->registrationData['email'])
                        ->subject('SLIA Conference 2026 - Registration Confirmation');
                
                if ($ccEmail = env('MAIL_ALWAYS_CC', 'sliaanualevents@gmail.com')) {
                    $message->cc($ccEmail);
                }

                $message->attachData($pdfContent, 
                            'Conference-Pass-' . $identifier . '.pdf',
                            ['mime' => 'application/pdf']
                        );

                // Decode and attach QR as PNG
                $qrData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->qrCode));
                $message->attachData($qrData, 
                            'QR-Code-' . $identifier . '.png',
                            ['mime' => 'image/png']
                        );
            });

            Log::info('Conference Queued email sent successfully', ['registration_id' => $this->registrationId]);

        } catch (\Exception $e) {
            Log::error('Conference Queue job failed: ' . $e->getMessage(), [
                'registration_id' => $this->registrationId,
                'attempt' => $this->attempts()
            ]);

            if ($this->attempts() >= $this->tries) {
                $this->sendFailureAlert();
            }

            throw $e;
        }
    }

    private function sendFailureAlert()
    {
        try {
            $adminEmail = env('ADMIN_EMAIL', 'sliaanualevents@gmail.com');
            
            if ($adminEmail) {
                $email = $this->registrationData['email'] ?? 'unknown';
                $membership = $this->registrationData['membership_number'] ?? 'unknown';

                Mail::raw("Conference Queued email delivery failed after {$this->tries} attempts.\n\n" .
                         "Registration ID: {$this->registrationId}\n" .
                         "Email: {$email}\n" .
                         "Membership: {$membership}\n" .
                         "Failed at: " . now()->toDateTimeString(),
                    function ($message) use ($adminEmail) {
                        $message->to($adminEmail)
                                ->subject('[URGENT] Conference Email Queue Delivery Failed');
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin alert: ' . $e->getMessage());
        }
    }
}
