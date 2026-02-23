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
        Schema::create('fellowship_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('membership_number', 50)->nullable();
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->string('phone', 20);
            $table->enum('category', ['slia_member', 'general_public', 'international', 'test_user']);
            $table->boolean('member_verified')->default(0);
            $table->string('nic_passport', 50)->nullable();
            $table->string('payment_reqid', 100)->nullable();
            $table->string('payment_ref_no', 100)->nullable();
            $table->enum('payment_status', ['pending', 'initiated', 'completed', 'failed'])->default('pending');
            $table->longText('payment_response')->nullable();
            $table->boolean('attended')->default(0);
            $table->timestamp('check_in_time')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('registration_fee', 10, 2)->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('membership_number');
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
        Schema::dropIfExists('fellowship_registrations');
    }
};
