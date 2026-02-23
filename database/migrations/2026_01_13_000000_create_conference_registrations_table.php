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
            $table->bigIncrements('id');
            $table->string('membership_number', 50)->nullable();
            $table->string('student_id', 50)->nullable(); // For student category
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->string('phone', 20);
            $table->enum('category', ['slia_member', 'student', 'general_public', 'international', 'test_user']);
            $table->boolean('member_verified')->default(0);
            $table->string('nic_passport', 50)->nullable();
            $table->string('payment_reqid', 100)->nullable();
            $table->string('payment_ref_no', 100)->nullable();
            $table->enum('payment_status', ['pending', 'initiated', 'completed', 'failed'])->default('pending');
            $table->longText('payment_response')->nullable();
            $table->boolean('include_lunch')->default(1);
            $table->enum('meal_preference', ['veg', 'non_veg'])->nullable();
            $table->boolean('food_received')->default(0);
            $table->boolean('attended')->default(0);
            $table->timestamp('check_in_time')->nullable();
            $table->boolean('concession_eligible')->default(0);
            $table->boolean('concession_applied')->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->decimal('lunch_fee', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('membership_number');
            $table->unique('student_id'); // Ensure student ID uniqueness
            $table->index('email');
            $table->index('category');
            $table->index('payment_status');
            $table->index('created_at');
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