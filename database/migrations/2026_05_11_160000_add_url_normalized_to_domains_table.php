<?php

declare(strict_types=1);

use App\Models\Domain;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->string('url_normalized', 2048)->nullable()->after('url');
        });

        Domain::query()
            ->orderBy('id')
            ->chunkById(200, function ($domains): void {
                foreach ($domains as $domain) {
                    $domain->forceFill([
                        'url_normalized' => Domain::normalizeUrl($domain->url),
                    ])->saveQuietly();
                }
            });

        Schema::table('domains', function (Blueprint $table): void {
            $table->unique(['user_id', 'url_normalized'], 'domains_user_url_normalized_unique');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropUnique('domains_user_url_normalized_unique');
            $table->dropColumn('url_normalized');
        });
    }
};
