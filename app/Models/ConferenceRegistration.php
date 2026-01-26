<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConferenceRegistration extends Model
{
    use HasFactory;

    protected $table = 'conference_registrations';

    protected $fillable = [
        'membership_number',
        'full_name',
        'email',
        'phone',
        'category',
        'member_verified',
        'nic_passport',
        'payment_reqid',
        'payment_ref_no',
        'payment_status',
        'payment_response',
        'include_lunch',
        'meal_preference',
        'food_received',
        'attended',
        'check_in_time',
        'concession_eligible',
        'concession_applied',
    ];

    protected $casts = [
        'member_verified' => 'boolean',
        'include_lunch' => 'boolean',
        'food_received' => 'boolean',
        'attended' => 'boolean',
        'concession_eligible' => 'boolean',
        'concession_applied' => 'boolean',
        'check_in_time' => 'datetime',
        'payment_response' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Category labels
    public function getCategoryLabelAttribute()
    {
        return match($this->category) {
            'slia_member' => 'SLIA Member',
            'general_public' => 'General Public',
            'international' => 'International',
            default => 'Unknown',
        };
    }

    // Payment status labels
    public function getPaymentStatusLabelAttribute()
    {
        return match($this->payment_status) {
            'pending' => 'Pending',
            'initiated' => 'Payment Initiated',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => 'Unknown',
        };
    }

    // Check if it's a member
    public function isMember()
    {
        return $this->category === 'slia_member';
    }

    // Check if requires NIC/Passport
    public function requiresNicPassport()
    {
        return !$this->isMember();
    }

    // Mark as paid
    public function markAsPaid($paymentRefNo, $response = null)
    {
        $this->update([
            'payment_ref_no' => $paymentRefNo,
            'payment_status' => 'completed',
            'payment_response' => $response
        ]);
    }

    // Mark as attended
    public function markAsAttended()
    {
        $this->update([
            'attended' => true,
            'check_in_time' => now(),
        ]);
    }

    // Mark as food received
    public function markFoodReceived()
    {
        if ($this->include_lunch) {
            $this->update(['food_received' => true]);
        }
    }

    // Apply concession
    public function applyConcession()
    {
        if ($this->concession_eligible && $this->attended && !$this->concession_applied) {
            $this->update(['concession_applied' => true]);
        }
    }

    // Scope: Members only
    public function scopeMembers($query)
    {
        return $query->where('category', 'slia_member');
    }

    // Scope: Attended
    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }

    // Scope: With lunch
    public function scopeWithLunch($query)
    {
        return $query->where('include_lunch', true);
    }

    // Scope: Food not received
    public function scopeFoodNotReceived($query)
    {
        return $query->where('include_lunch', true)->where('food_received', false);
    }

    // Scope: Paid registrations
    public function scopePaid($query)
    {
        return $query->where('payment_status', 'completed');
    }

    // Scope: Pending payments
    public function scopePendingPayment($query)
    {
        return $query->where('payment_status', 'pending');
    }


    
}