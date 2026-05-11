<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CheckStatus;
use App\Enums\HttpMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DomainCheck extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'domain_id',
        'status',
        'http_status',
        'response_time_ms',
        'method',
        'error',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CheckStatus::class,
            'method' => HttpMethod::class,
            'http_status' => 'integer',
            'response_time_ms' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }
}
