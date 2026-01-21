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

class SendInaugurationPassEmail implements ShouldQueue
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
            $pdf = Pdf::loadView('pdf.inauguration-pass', [
                'membership' => $this->registrationData['membership_number'],
                'name' => $this->registrationData['full_name'],
                'email' => $this->registrationData['email'],
                'mobile' => $this->registrationData['mobile'],
                'meal_preference' => $this->registrationData['meal_preference'],
                'qr' => $this->qrCode,
                'date' => now()->format('F j, Y'),
                'time' => now()->format('h:i A'),
                'registration_id' => $this->registrationId,
                'queued' => true
            ]);

            $pdfContent = $pdf->output();

            // Send email
            Mail::send('emails.inauguration-pass-queued', [
                'name' => $this->registrationData['full_name'],
                'membership' => $this->registrationData['membership_number'],
                'email' => $this->registrationData['email'],
                'date' => now()->format('F j, Y'),
                'queued' => true
            ], function ($message) use ($pdfContent) {
                $message->to($this->registrationData['email'])
                        ->subject('SLIA Inauguration Pass (Queued Delivery)')
                        ->attachData($pdfContent, 
                            'SLIA-Inauguration-Pass-' . $this->registrationData['membership_number'] . '.pdf'
                        );
            });

            Log::info('Queued email sent successfully', [
                'registration_id' => $this->registrationId,
                'email' => $this->registrationData['email'],
                'queue_time' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('Queue job failed: ' . $e->getMessage(), [
                'registration_id' => $this->registrationId,
                'email' => $this->registrationData['email'] ?? 'unknown',
                'attempt' => $this->attempts()
            ]);
            
            // If failed all attempts, send admin alert
            if ($this->attempts() >= $this->tries) {
                $this->sendFailureAlert();
            }
            
            throw $e; // Let Laravel handle retry
        }
    }

    private function sendFailureAlert()
    {
        try {
            $adminEmail = env('ADMIN_EMAIL', 'admin@sliainauguration.com');
            
            if ($adminEmail) {
                Mail::raw("Queued email delivery failed after {$this->tries} attempts.\n\n" .
                         "Registration ID: {$this->registrationId}\n" .
                         "Email: {$this->registrationData['email']}\n" .
                         "Membership: {$this->registrationData['membership_number']}\n" .
                         "Failed at: " . now()->toDateTimeString(),
                    function ($message) use ($adminEmail) {
                        $message->to($adminEmail)
                                ->subject('[URGENT] Email Queue Delivery Failed');
                    }
                );
            }
        } catch (\Exception $e) {
            Log::error('Failed to send admin alert: ' . $e->getMessage());
        }
    }
}