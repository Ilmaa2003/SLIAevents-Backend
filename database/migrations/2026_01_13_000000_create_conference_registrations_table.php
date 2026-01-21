<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conference_registrations', function (Blueprint $table) {
            $table->id();
            
            // Member Info
            $table->string('membership_number')->nullable();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone');
            
            // Registration Details
            $table->enum('category', ['slia_member', 'general_public', 'international']);
            $table->boolean('member_verified')->default(false);
            $table->string('nic_passport')->nullable(); // For non-members only
            
            // Payment & Food
            $table->string('payment_ref_no')->nullable();
            $table->boolean('include_lunch')->default(false);
            $table->boolean('food_received')->default(false);
            
            // Attendance
            $table->boolean('attended')->default(false);
            $table->timestamp('check_in_time')->nullable();
            
            // Concession
            $table->boolean('concession_eligible')->default(false);
            $table->boolean('concession_applied')->default(false);
            
            // Timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('membership_number');
            $table->index('email');
            $table->index('payment_ref_no');
            $table->index('attended');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conference_registrations');
    }
};