<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_no')->unique();
            $table->string('backup_type', 40)->default('database_only');
            $table->string('dump_format', 40)->default('sql_zip');
            $table->string('status', 40)->default('ready');
            $table->string('disk', 40)->default('local');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('restored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_backups');
    }
};
