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
        Schema::create('exhibition_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('membership_number')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('mobile');
            $table->boolean('attended')->default(false);
            $table->timestamp('check_in_time')->nullable();
            $table->timestamps();
            
            // Index for faster queries
            $table->index('membership_number');
            $table->index('email');
            $table->index('attended');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exhibition_registrations');
    }
};