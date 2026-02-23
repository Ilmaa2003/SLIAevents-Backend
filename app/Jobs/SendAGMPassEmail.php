<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class SendAGMPassEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [60, 300, 600]; // Retry after 1, 5, 10 minutes
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
            // Regenerate PDF (in case of queue delay)
            // Using logic from AGMRegistrationController
            $pdf = Pdf::loadView('pdf.agm-pass', [
                'registration_id' => $this->registrationId,
                'membership' => $this->registrationData['membership_number'],
                'name' => $this->registrationData['full_name'],
                'email' => $this->registrationData['email'],
                'mobile' => $this->registrationData['mobile'],
                'meal_preference' => $this->registrationData['meal_preference'],
                'qr' => $this->qrCode,
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'event_name' => 'Annual General Meeting',
                'event_date' => 'December 15, 2024',
                'event_time' => '10:00 AM - 2:00 PM',
                'venue' => 'Main Auditorium, Conference Center',
                // AGM controller logic implies these fields are passed to valid view
                'attended' => false, 
                'meal_received' => false
            ]);

            $pdfContent = $pdf->output();

            // Send email
            Mail::send('emails.agm-pass', [
                'name' => $this->registrationData['full_name'],
                'membership' => $this->registrationData['membership_number'],
                'email' => $this->registrationData['email'],
                'meal_preference' => $this->registrationData['meal_preference'],
                'registration_date' => now()->format('F j, Y'),
                'registration_time' => now()->format('h:i A'),
                'event_date' => 'December 15, 2024',
                'event_time' => '10:00 AM - 2:00 PM',
                'venue' => 'Main Auditorium, Conference Center'
            ], function ($message) use ($pdfContent) {
                $message->to($this->registrationData['email'])
                        ->subject('AGM Registration Confirmation & Attendance Pass');
                
                if ($ccEmail = env('MAIL_ALWAYS_CC')) {
                    $message->cc($ccEmail);
                }

                $message->attachData($pdfContent, 
                            'AGM-Registration-Pass-' . $this->registrationData['membership_number'] . '.pdf',
                            ['mime' => 'application/pdf']
                        );
            });

            Log::info('AGM Queued email sent successfully', [
                'registration_id' => $this->registrationId,
                'email' => $this->registrationData['email'],
                'queue_time' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('AGM Queue job failed: ' . $e->getMessage(), [
                'registration_id' => $this->registrationId,
                'email' => $this->registrationData['email'] ?? 'unknown',
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

                Mail::raw("AGM Queued email delivery failed after {$this->tries} attempts.\n\n" .
                         "Registration ID: {$this->registrationId}\n" .
                         "Email: {$email}\n" .
                         "Membership: {$membership}\n" .
                         "Failed at: " . now()->toDateTimeString(),
                    function ($message) use ($adminEmail) {
                        $message->to($adminEmail)
                                ->subject('[URGENT] AGM Email Queue Delivery Failed');
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin alert: ' . $e->getMessage());
        }
    }
}
