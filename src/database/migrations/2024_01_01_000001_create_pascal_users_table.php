<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pascal_users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();           // DocType primary key (e.g. "john-doe")
            $table->tinyInteger('docstatus')->default(0);    // 0=Draft (always 0 for User)
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['user', 'manager', 'admin'])->default('user');
            $table->enum('status', ['Active', 'Inactive', 'Banned'])->default('Active');
            $table->string('avatar')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->rememberToken();
            $table->string('owner', 255)->nullable();        // Pascal: who created this record
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pascal_users');
    }
};
