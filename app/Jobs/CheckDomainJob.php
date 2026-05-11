<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Domain;
use App\Notifications\DomainStatusChanged;
use App\Services\DomainProbe;
use App\Services\ProbeResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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

        $key = $domain->url_normalized ?? Domain::normalizeUrl($domain->url);
        $lock = Cache::lock(
            'domain-check:'.md5(($key ?? '').'|'.$domain->method->value),
            60,
        );

        if (! $lock->get()) {
            // Another worker is already probing the same URL+method; that run
            // will fan its result out to this domain too, so we just exit.
            return;
        }

        try {
            $result = $probe->probe($domain);
            $checkedAt = Carbon::now();

            $this->applyResult($domain, $result, $checkedAt);

            Domain::query()
                ->with('user')
                ->where('id', '!=', $domain->id)
                ->where('url_normalized', $domain->url_normalized)
                ->where('method', $domain->method->value)
                ->where('is_active', true)
                ->each(function (Domain $sibling) use ($result, $checkedAt): void {
                    $this->applyResult($sibling, $result, $checkedAt);
                });
        } finally {
            $lock->release();
        }
    }

    private function applyResult(Domain $domain, ProbeResult $result, Carbon $checkedAt): void
    {
        $previousStatus = $domain->last_status;

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
