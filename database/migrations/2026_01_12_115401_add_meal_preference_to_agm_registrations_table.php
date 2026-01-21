<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agm_registrations', function (Blueprint $table) {
            $table->enum('meal_preference', ['veg', 'non_veg'])->nullable()->after('mobile');
        });
    }

    public function down(): void
    {
        Schema::table('agm_registrations', function (Blueprint $table) {
            $table->dropColumn('meal_preference');
        });
    }
};