<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('inauguration_registrations', function (Blueprint $table) {
            $table->boolean('attended')->default(false);
            $table->boolean('meal_collected')->default(false);
        });
    }

    public function down()
    {
        Schema::table('inauguration_registrations', function (Blueprint $table) {
            $table->dropColumn(['attended', 'meal_collected']);
        });
    }
};
