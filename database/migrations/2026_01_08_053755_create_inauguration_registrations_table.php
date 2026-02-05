<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inauguration_registrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('membership_number');
            $table->string('full_name');
            $table->string('email');
            $table->string('mobile');
            $table->enum('meal_preference', ['veg', 'non_veg']);
            $table->boolean('attended')->default(0);
            $table->boolean('meal_received')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inauguration_registrations');
    }
};