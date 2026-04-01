<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit trail — every DocType change is recorded here
        Schema::create('pascal_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doctype', 120);
            $table->string('docname', 255);
            $table->enum('action', ['create', 'update', 'delete', 'submit', 'cancel']);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_email')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->json('diff')->nullable();
            $table->timestamp('created_at');

            $table->index(['doctype', 'docname']);
            $table->index('user_id');
            $table->index('created_at');
        });

        // Login history for User module
        Schema::create('pascal_login_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->enum('status', ['success', 'failed', 'blocked'])->default('success');
            $table->string('failure_reason')->nullable();
            $table->timestamp('logged_in_at');
            $table->timestamp('logged_out_at')->nullable();

            $table->index(['user_id', 'logged_in_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pascal_login_histories');
        Schema::dropIfExists('pascal_audit_logs');
    }
};
