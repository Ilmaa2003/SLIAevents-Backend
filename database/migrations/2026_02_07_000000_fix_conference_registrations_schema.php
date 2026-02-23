<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('conference_registrations', function (Blueprint $table) {
            // 1. Make membership_number nullable
            $table->string('membership_number', 50)->nullable()->change();

            // 2. Add student_id after membership_number if it doesn't exist
            if (!Schema::hasColumn('conference_registrations', 'student_id')) {
                $table->string('student_id', 50)->nullable()->after('membership_number');
                $table->unique('student_id');
            }

            // 3. Update category enum
            // Note: In some databases (like MariaDB/MySQL), modifying an ENUM is easiest with a raw query
            $newCategories = "'slia_member', 'student', 'general_public', 'international', 'test_user'";
            DB::statement("ALTER TABLE conference_registrations MODIFY COLUMN category ENUM($newCategories) NOT NULL");

            // 4. Add missing financial columns
            if (!Schema::hasColumn('conference_registrations', 'registration_fee')) {
                $table->decimal('registration_fee', 10, 2)->default(0)->after('concession_applied');
            }
            if (!Schema::hasColumn('conference_registrations', 'lunch_fee')) {
                $table->decimal('lunch_fee', 10, 2)->default(0)->after('registration_fee');
            }
            if (!Schema::hasColumn('conference_registrations', 'total_amount')) {
                $table->decimal('total_amount', 10, 2)->default(0)->after('lunch_fee');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conference_registrations', function (Blueprint $table) {
            // Revert membership_number to NOT NULL (be careful if there are null values)
            // Revert category enum
            $oldCategories = "'slia_member', 'general_public', 'international'";
            DB::statement("ALTER TABLE conference_registrations MODIFY COLUMN category ENUM($oldCategories) NOT NULL");
            
            // Drop columns
            $table->dropColumn(['student_id', 'registration_fee', 'lunch_fee', 'total_amount']);
        });
    }
};
