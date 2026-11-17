<?php

namespace Modules\Events\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventPass;

class EventPassExpiryService
{
    /** Expire every active pass for an event once its configured end time has passed. */
    public function expireEvent(int $eventId, ?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();

        return DB::transaction(function () use ($eventId, $asOf): int {
            // Match attendance's lock order: event first, then pass rows.
            $event = Event::query()->lockForUpdate()->find($eventId);

            if (! $event || ! $event->ends_at || $event->ends_at->greaterThan($asOf)) {
                return 0;
            }

            return EventPass::query()
                ->where('organization_id', $event->organization_id)
                ->where('event_id', $event->id)
                ->where('status', 'active')
                ->update([
                    'status' => 'expired',
                    'updated_at' => $asOf,
                ]);
        });
    }

    /** Process all ended events in bounded batches; safe to run repeatedly. */
    public function expireEndedEvents(?CarbonInterface $asOf = null): int
    {
        $asOf ??= now();
        $expired = 0;

        Event::query()
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $asOf)
            ->whereHas('passes', fn ($query) => $query->where('status', 'active'))
            ->orderBy('id')
            ->chunkById(100, function ($events) use ($asOf, &$expired): void {
                foreach ($events as $event) {
                    $expired += $this->expireEvent((int) $event->id, $asOf);
                }
            });

        return $expired;
    }
}
