<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InaugurationRegistration extends Model
{
    use HasFactory;

    protected $table = 'inauguration_registrations';

    protected $fillable = [
        'membership_number',
        'full_name',
        'email',
        'mobile',
        'meal_preference',
        'attended',
        'meal_received',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'meal_received' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Mark as attended
    public function markAsAttended()
    {
        $this->update([
            'attended' => true,
            'meal_received' => $this->meal_preference ? true : false,
        ]);
    }

    // Mark meal as received
    public function markMealReceived()
    {
        if ($this->meal_preference) {
            $this->update(['meal_received' => true]);
        }
    }

    // Scope: Attended
    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }

    // Scope: Meal not received
    public function scopeMealNotReceived($query)
    {
        return $query->whereNotNull('meal_preference')
                    ->where('attended', true)
                    ->where('meal_received', false);
    }
}