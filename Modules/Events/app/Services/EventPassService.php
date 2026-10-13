<?php

namespace Modules\Events\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Models\EventPass;
use Modules\Events\Jobs\DeliverEventPassEmail;
use Modules\Member\Models\Member;
use Illuminate\Support\Str;

class EventPassService
{
    /**
     * Issue one bearer pass per participant. Only the hash is persisted; the raw token is
     * returned once to the authorized issuer. The QR email is queued using an encrypted
     * temporary QR artifact; neither the token nor recipient address is stored in the job payload.
     */
    public function issue(Organization $organization, Event $event, EventParticipant $participant, ?int $issuerId): array
    {
        if ((int) $event->organization_id !== (int) $organization->id
            || (int) $participant->organization_id !== (int) $organization->id
            || (int) $participant->event_id !== (int) $event->id) {
            abort(404);
        }

        if ($event->status !== EventStatus::Published) {
            throw ValidationException::withMessages(['event' => 'Passes can only be issued for published events.']);
        }

        if (in_array($participant->status, ['cancelled', 'declined'], true)) {
            throw ValidationException::withMessages(['participant' => 'A cancelled or declined participant cannot receive a pass.']);
        }

        $existing = EventPass::query()->where('event_participant_id', $participant->id)->first();
        if ($existing) {
            throw ValidationException::withMessages(['participant' => 'A pass has already been issued to this participant.']);
        }

        $token = Str::random(64);
        $pass = DB::transaction(fn () => EventPass::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'event_participant_id' => $participant->id,
            'token_hash' => hash('sha256', $token),
            'status' => 'active',
            'capacity' => 1,
            'issued_at' => now(),
            'issued_by' => $issuerId,
            'notification_delivery_status' => 'not_requested',
        ]));

        $email = $participant->email;
        if (! $email && $participant->member_id) {
            $member = Member::query()->with('user')->find($participant->member_id);
            $email = $member?->user?->email;
        }

        if ($email) {
            $qrPath = 'events/pass-delivery/'.Str::uuid().'.enc';
            try {
                // Encrypt the rendered QR SVG at rest. The raw bearer token and QR contents
                // are never included in the queued job payload.
                $qrSvg = (string) \QrCode::margin(1)
                    ->color(0, 0, 0)
                    ->backgroundColor(255, 255, 255, 1)
                    ->generate($token);

                $stored = Storage::disk('local')->put($qrPath, Crypt::encryptString($qrSvg));
                if (! $stored) {
                    throw new \RuntimeException('Unable to store the encrypted event pass QR artifact.');
                }
                $pass->forceFill(['notification_delivery_status' => 'queued'])->save();
                DeliverEventPassEmail::dispatch($pass->id, $qrPath)->afterCommit();
            } catch (\Throwable $exception) {
                Storage::disk('local')->delete($qrPath);
                $pass->forceFill([
                    'notification_delivery_status' => 'failed',
                    'notification_failed_at' => now(),
                ])->save();
                Log::error('Unable to queue event pass email.', [
                    'event_pass_id' => $pass->id,
                    'exception' => $exception::class,
                ]);
            }
        }

        $pass->refresh();

        return [
            'pass' => $pass,
            'token' => $token,
            'qr_payload' => $token,
            'notification_queued_to' => $email,
            'notification_status' => $pass->notification_delivery_status,
        ];
    }

    public function revoke(Organization $organization, Event $event, EventPass $pass): EventPass
    {
        if ((int) $event->organization_id !== (int) $organization->id
            || (int) $pass->organization_id !== (int) $organization->id
            || (int) $pass->event_id !== (int) $event->id) {
            abort(404);
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($organization, $event, $pass): EventPass {
            $lockedPass = EventPass::query()
                ->where('organization_id', $organization->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->findOrFail($pass->id);

            if ($lockedPass->status === 'revoked') {
                return $lockedPass;
            }

            if ((int) $lockedPass->current_inside > 0) {
                throw ValidationException::withMessages([
                    'pass' => 'A pass cannot be revoked while people are recorded inside the event.',
                ]);
            }

            $lockedPass->status = 'revoked';
            $lockedPass->revoked_at = now();
            $lockedPass->save();

            return $lockedPass->refresh();
        });
    }



    /**
     * Safely retry a failed pass email by rotating the bearer token. The old QR
     * token becomes invalid immediately; the replacement token is never returned
     * by this endpoint and is only delivered through the queued email.
     */
    public function retryDelivery(Organization $organization, Event $event, EventPass $pass): EventPass
    {
        if ((int) $event->organization_id !== (int) $organization->id
            || (int) $pass->organization_id !== (int) $organization->id
            || (int) $pass->event_id !== (int) $event->id) {
            abort(404);
        }

        if ($event->status !== EventStatus::Published) {
            throw ValidationException::withMessages(['event' => 'Pass delivery can only be retried for a published event.']);
        }

        $participant = $pass->participant;
        if (! $participant || in_array($participant->status, ['cancelled', 'declined'], true)) {
            throw ValidationException::withMessages(['participant' => 'A cancelled or declined participant cannot receive a pass.']);
        }

        $recipient = $participant->email;
        if (! $recipient && $participant->member_id) {
            $member = Member::query()->with('user')->find($participant->member_id);
            $recipient = $member?->user?->email;
        }
        if (! $recipient) {
            throw ValidationException::withMessages(['notification' => 'The participant has no deliverable email address.']);
        }

        $newToken = Str::random(64);
        $lockedPass = DB::transaction(function () use ($organization, $event, $pass, $newToken): EventPass {
            $locked = EventPass::query()
                ->where('organization_id', $organization->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->findOrFail($pass->id);

            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['pass' => 'Only active passes can be retried.']);
            }
            if ((int) $locked->current_inside > 0) {
                throw ValidationException::withMessages(['pass' => 'Pass delivery cannot be retried while people are recorded inside the event.']);
            }
            if ($locked->notification_delivery_status !== 'failed') {
                throw ValidationException::withMessages(['notification' => 'Only failed pass deliveries can be retried.']);
            }

            // Rotate the hash so any previously issued token is no longer valid.
            $locked->token_hash = hash('sha256', $newToken);
            $locked->notification_delivery_status = 'queued';
            $locked->notification_sent_at = null;
            $locked->notification_failed_at = null;
            $locked->save();

            return $locked;
        });

        $qrPath = 'events/pass-delivery/'.Str::uuid().'.enc';
        try {
            $qrSvg = (string) \QrCode::margin(1)
                ->color(0, 0, 0)
                ->backgroundColor(255, 255, 255, 1)
                ->generate($newToken);

            if (! Storage::disk('local')->put($qrPath, Crypt::encryptString($qrSvg))) {
                throw new \RuntimeException('Unable to store the encrypted event pass QR artifact.');
            }

            DeliverEventPassEmail::dispatch($lockedPass->id, $qrPath)->afterCommit();
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($qrPath);
            $lockedPass->forceFill([
                'notification_delivery_status' => 'failed',
                'notification_failed_at' => now(),
            ])->save();
            Log::error('Unable to retry event pass email.', [
                'event_pass_id' => $lockedPass->id,
                'exception' => $exception::class,
            ]);
            throw ValidationException::withMessages(['notification' => 'The pass email could not be queued. Please retry later.']);
        }

        return $lockedPass->refresh();
    }

    /** Return organization-scoped delivery counts and a paginated failure list. */
    public function deliveryStatus(Organization $organization, Event $event): array
    {
        if ((int) $event->organization_id !== (int) $organization->id) {
            abort(404);
        }

        $base = EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id);

        $counts = (clone $base)
            ->selectRaw('notification_delivery_status, COUNT(*) as aggregate')
            ->groupBy('notification_delivery_status')
            ->pluck('aggregate', 'notification_delivery_status');

        $failed = (clone $base)
            ->where('notification_delivery_status', 'failed')
            ->with('participant:id,name,email,event_id')
            ->orderByDesc('notification_failed_at')
            ->paginate(20);

        $failed->getCollection()->transform(function (EventPass $pass): array {
            return [
                'pass_id' => $pass->id,
                'participant_id' => $pass->event_participant_id,
                'participant_name' => $pass->participant?->name,
                'status' => $pass->notification_delivery_status,
                'failed_at' => $pass->notification_failed_at,
                'retryable' => $pass->status === 'active' && (int) $pass->current_inside === 0,
            ];
        });

        return [
            'counts' => [
                'not_requested' => (int) ($counts['not_requested'] ?? 0),
                'queued' => (int) ($counts['queued'] ?? 0),
                'sending' => (int) ($counts['sending'] ?? 0),
                'sent' => (int) ($counts['sent'] ?? 0),
                'failed' => (int) ($counts['failed'] ?? 0),
                'cancelled' => (int) ($counts['cancelled'] ?? 0),
            ],
            'failed' => $failed,
        ];
    }

    public function findByToken(Organization $organization, string $token): ?EventPass
    {
        if (strlen($token) < 40 || strlen($token) > 128) {
            return null;
        }

        // Pass validation must be tenant-scoped even when the bearer token is valid.
        $pass = EventPass::query()
            ->where('organization_id', $organization->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->with(['event', 'participant'])
            ->first();

        if ($pass && $pass->event->ends_at && $pass->event->ends_at->isPast()) {
            return null;
        }

        return $pass;
    }
}
