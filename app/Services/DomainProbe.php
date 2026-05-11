<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckStatus;
use App\Models\Domain;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class DomainProbe
{
    public function probe(Domain $domain): ProbeResult
    {
        $startedAt = microtime(true);
        $timeoutSeconds = max(1, (int) ceil($domain->timeout_ms / 1000));

        try {
            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout($timeoutSeconds)
                ->withUserAgent('Monitool/1.0 (+health-check)')
                ->withOptions(['allow_redirects' => true, 'http_errors' => false])
                ->send($domain->method->value, $domain->url);

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $httpStatus = $response->status();
            $status = ($httpStatus >= 200 && $httpStatus < 400) ? CheckStatus::Up : CheckStatus::Down;

            return new ProbeResult(
                status: $status,
                httpStatus: $httpStatus,
                responseTimeMs: $elapsedMs,
                error: $status === CheckStatus::Down ? "Unexpected HTTP status {$httpStatus}" : null,
            );
        } catch (ConnectionException $e) {
            return new ProbeResult(
                status: CheckStatus::Down,
                httpStatus: null,
                responseTimeMs: (int) round((microtime(true) - $startedAt) * 1000),
                error: $this->trimError($e->getMessage()),
            );
        } catch (Throwable $e) {
            return new ProbeResult(
                status: CheckStatus::Down,
                httpStatus: null,
                responseTimeMs: (int) round((microtime(true) - $startedAt) * 1000),
                error: $this->trimError($e->getMessage()),
            );
        }
    }

    private function trimError(string $message): string
    {
        return mb_substr($message, 0, 1000);
    }
}
