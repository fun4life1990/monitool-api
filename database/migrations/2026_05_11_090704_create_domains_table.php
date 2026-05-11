<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('url');
            $table->string('method', 8)->default('GET');
            $table->unsignedInteger('interval_seconds')->default(300);
            $table->unsignedInteger('timeout_ms')->default(5000);
            $table->string('notify_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_status', 8)->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
