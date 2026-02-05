<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_details', function (Blueprint $table) {
            $table->integer('entry id'); // Note: The space in column name from SQL
            $table->binary('pic')->nullable();
            $table->string('full_name')->nullable();
            $table->string('membership_no')->nullable();
            $table->string('ininame')->nullable();
            $table->string('pqaa')->nullable();
            $table->string('aquaf')->nullable();
            $table->string('arb')->nullable();
            $table->string('mcategory')->nullable();
            $table->string('district')->nullable();
            $table->string('sliafm')->nullable();
            $table->string('oadd')->nullable();
            $table->string('otel')->nullable();
            $table->string('offfax')->nullable();
            $table->string('oemail')->nullable();
            $table->string('oweb')->nullable();
            $table->string('personal_address')->nullable();
            $table->string('personal_mobilenumber')->nullable();
            $table->string('refax')->nullable();
            $table->string('personal_email')->nullable();
            $table->string('oradd')->nullable();
            $table->string('ortel')->nullable();
            $table->string('oremail')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('gender')->nullable();
            $table->string('postalemail')->nullable();
            $table->string('postaladdress')->nullable();
            $table->longText('otheraddress')->nullable();
            $table->longText('opheld')->nullable();
            $table->longText('sliaawards')->nullable();
            $table->longText('aoawards')->nullable();
            $table->string('nic')->nullable();
            $table->string('attendence')->nullable();
            $table->string('arbattendence')->nullable();
            $table->string('pyear')->nullable();
            $table->longText('invoice')->nullable();
            $table->string('pcategory')->nullable();
            $table->longText('practice')->nullable();
            $table->string('pname')->nullable();
            $table->string('dateofbirth')->nullable();
            $table->string('laps')->nullable();
            $table->date('datetime')->nullable();
            $table->date('date')->nullable();
            $table->longText('extraone')->nullable();
            $table->longText('extratwo')->nullable();
            $table->longText('extrathree')->nullable();
            $table->longText('extrafour')->nullable();
            $table->longText('extrafive')->nullable();
            $table->date('dateone')->nullable();
            $table->date('datetwo')->nullable();
            $table->date('datethree')->nullable();
            $table->dateTime('datefour')->nullable();
            $table->dateTime('datefive')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_details');
    }
};
