<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AGMRegistration extends Model
{
    use HasFactory;

    protected $table = 'agm_registrations';

    protected $fillable = [
        'membership_number',
        'full_name',
        'email',
        'mobile',
        'meal_preference',
        'attended',
        'meal_received',
        'check_in_time',
    ];

    protected $casts = [
        'attended' => 'boolean',
        'meal_received' => 'boolean',
        'check_in_time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Mark as attended
    public function markAsAttended()
    {
        $this->update([
            'attended' => true,
            'check_in_time' => now(),
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

    // Get meal preference label
    public function getMealPreferenceLabelAttribute()
    {
        return match($this->meal_preference) {
            'veg' => 'Vegetarian',
            'non_veg' => 'Non-Vegetarian',
            default => 'Not Specified',
        };
    }
}