<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('domain_id')->constrained()->cascadeOnDelete();
            $table->string('status', 8);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('method', 8);
            $table->text('error')->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['domain_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_checks');
    }
};
