<?php

namespace Modules\Events\Console\Commands;

use Illuminate\Console\Command;
use Modules\Events\Services\EventPassExpiryService;

class ExpireEventPasses extends Command
{
    protected $signature = 'events:expire-passes';

    protected $description = 'Expire active event passes after their event end time.';

    public function handle(EventPassExpiryService $expiry): int
    {
        $count = $expiry->expireEndedEvents();

        $this->info("Expired {$count} event pass(es).");

        return self::SUCCESS;
    }
}
