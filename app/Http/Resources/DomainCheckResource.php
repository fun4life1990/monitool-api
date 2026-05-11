<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DomainCheck;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DomainCheck
 */
class DomainCheckResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'http_status' => $this->http_status,
            'response_time_ms' => $this->response_time_ms,
            'method' => $this->method->value,
            'error' => $this->error,
            'checked_at' => $this->checked_at->toIso8601String(),
        ];
    }
}
