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
}
