<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckStatus;
use App\Enums\HttpMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'method',
        'interval_seconds',
        'timeout_ms',
        'notify_email',
        'is_active',
        'last_status',
        'last_checked_at',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'method' => 'GET',
        'interval_seconds' => 300,
        'timeout_ms' => 5000,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'method' => HttpMethod::class,
            'interval_seconds' => 'integer',
            'timeout_ms' => 'integer',
            'is_active' => 'boolean',
            'last_status' => CheckStatus::class,
            'last_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Domain $domain): void {
            if ($domain->isDirty('url') || $domain->url_normalized === null) {
                $domain->url_normalized = self::normalizeUrl($domain->url);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function checks(): HasMany
    {
        return $this->hasMany(DomainCheck::class);
    }

    public function notificationEmail(): string
    {
        return $this->notify_email ?: $this->user->email;
    }

    /**
     * Canonical form used for the per-user uniqueness check and for matching
     * sibling rows across users when sharing probe results.
     *
     * Lowercases scheme and host, drops the default port (80 for http, 443 for
     * https), strips a trailing slash on the path, and discards the fragment.
     * Path and query are otherwise preserved case-sensitively.
     */
    public static function normalizeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return mb_strtolower($url);
        }

        $scheme = mb_strtolower($parts['scheme']);
        $host = mb_strtolower($parts['host']);
        $port = $parts['port'] ?? null;

        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        $path = rtrim($parts['path'] ?? '', '/');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $portPart = $port !== null ? ':'.$port : '';

        return $scheme.'://'.$host.$portPart.$path.$query;
    }
}
