<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('agm_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('membership_number')->unique();
            $table->string('full_name');
            $table->string('email');
            $table->string('mobile');
            $table->enum('meal_preference', ['veg', 'non_veg'])->nullable();
            $table->boolean('attended')->default(0);
            $table->boolean('meal_received')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('agm_registrations');
    }
};