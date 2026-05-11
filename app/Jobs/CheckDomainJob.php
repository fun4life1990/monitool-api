<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Notifications\DomainStatusChanged;
use App\Services\DomainProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class CheckDomainJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public int $domainId)
    {
        $this->onQueue('checks');
    }

    public function handle(DomainProbe $probe): void
    {
        /** @var Domain|null $domain */
        $domain = Domain::query()->with('user')->find($this->domainId);

        if ($domain === null || ! $domain->is_active) {
            return;
        }

        $result = $probe->probe($domain);

        $previousStatus = $domain->last_status;
        $checkedAt = Carbon::now();

        $domain->checks()->create([
            'status' => $result->status,
            'http_status' => $result->httpStatus,
            'response_time_ms' => $result->responseTimeMs,
            'method' => $domain->method,
            'error' => $result->error,
            'checked_at' => $checkedAt,
        ]);

        $domain->forceFill([
            'last_status' => $result->status,
            'last_checked_at' => $checkedAt,
        ])->save();

        if ($previousStatus !== null && $previousStatus !== $result->status) {
            Notification::route('mail', $domain->notificationEmail())
                ->notify(new DomainStatusChanged(
                    domainId: $domain->id,
                    url: $domain->url,
                    name: $domain->name,
                    previousStatus: $previousStatus,
                    currentStatus: $result->status,
                    httpStatus: $result->httpStatus,
                    error: $result->error,
                    checkedAt: $checkedAt,
                ));
        }
    }
}
