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
    public function up()
    {
        Schema::table('conference_registrations', function (Blueprint $table) {
            $table->enum('meal_preference', ['veg', 'non_veg'])->nullable()->after('include_lunch');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('conference_registrations', function (Blueprint $table) {
            $table->dropColumn('meal_preference');
        });
    }
};
