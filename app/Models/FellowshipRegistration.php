<?php
// File: FellowshipRegistration.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FellowshipRegistration extends Model
{
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
        'attended',
        'check_in_time',
        'total_amount',
        'registration_fee',
    ];

    protected $casts = [
        'member_verified' => 'boolean',
        'attended' => 'boolean',
        'check_in_time' => 'datetime',
        'total_amount' => 'decimal:2',
        'registration_fee' => 'decimal:2',
    ];
}
