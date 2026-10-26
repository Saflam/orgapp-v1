<?php

namespace Modules\Member\Console\Commands;

use Illuminate\Console\Command;
use Modules\Member\Enums\MembershipStatus;
use Modules\Member\Models\Membership;
use Modules\Member\Services\MemberPaymentService;

class GenerateMemberPaymentObligations extends Command
{
    protected $signature = 'member:generate-payment-obligations {--date= : Process obligations due on or before this date (YYYY-MM-DD). Defaults to today.}';

    protected $description = 'Generate due member payment obligations for all active memberships.';

    public function handle(MemberPaymentService $payments): int
    {
        $asOf = $this->option('date') ?: now()->toDateString();
        $created = 0;

        Membership::query()
            ->where('status', MembershipStatus::ACTIVE)
            ->chunkById(100, function ($memberships) use ($payments, $asOf, &$created): void {
                foreach ($memberships as $membership) {
                    $created += $payments->syncOutstandingObligations($membership, $asOf)->count();
                }
            });

        $this->info("Generated {$created} member payment obligation(s).");

        return self::SUCCESS;
    }
}
