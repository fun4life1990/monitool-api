<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckStatus;

final readonly class ProbeResult
{
    public function __construct(
        public CheckStatus $status,
        public ?int $httpStatus,
        public ?int $responseTimeMs,
        public ?string $error,
    ) {}
}
