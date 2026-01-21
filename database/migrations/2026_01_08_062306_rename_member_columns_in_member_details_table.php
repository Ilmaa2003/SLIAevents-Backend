<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('member_details', function (Blueprint $table) {
            $table->renameColumn('personal_email', 'personal_address');
            $table->renameColumn('remail', 'personal_email');
        });
    }

    public function down(): void {
        Schema::table('member_details', function (Blueprint $table) {
            $table->renameColumn('personal_address', 'personal_email');
            $table->renameColumn('personal_email', 'remail');
        });
    }
};
