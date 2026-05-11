<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CheckDomainJob;
use App\Models\Domain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('domains:dispatch-checks')]
#[Description('Queue background checks for domains whose interval has elapsed')]
class DispatchDomainChecksCommand extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        Domain::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('last_checked_at')
                    ->orWhereRaw('last_checked_at + (interval_seconds * interval \'1 second\') <= ?', [now()]);
            })
            ->orderBy('id')
            ->chunkById(100, function ($domains) use (&$dispatched): void {
                foreach ($domains as $domain) {
                    CheckDomainJob::dispatch($domain->id);
                    $dispatched++;
                }
            });

        $this->info("Dispatched {$dispatched} domain check job(s).");

        return self::SUCCESS;
    }
}
