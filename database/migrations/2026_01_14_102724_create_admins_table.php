<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('username', 50)->unique();
            $table->string('password', 255);
            $table->string('name', 100);
            $table->string('email', 100)->nullable();
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            $table->primary('id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};