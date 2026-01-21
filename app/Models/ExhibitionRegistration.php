<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExhibitionRegistration extends Model
{
    use HasFactory;

    protected $table = 'exhibition_registrations';

    protected $fillable = [
        'membership_number',
        'full_name',
        'email',
        'mobile',
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
        ]);
    }

    // Mark meal as received
    public function markMealReceived()
    {
        $this->update(['meal_received' => true]);
    }

    // Scope: Attended
    public function scopeAttended($query)
    {
        return $query->where('attended', true);
    }

    // Scope: Meal not received
    public function scopeMealNotReceived($query)
    {
        return $query->where('attended', true)
                    ->where('meal_received', false);
    }
}