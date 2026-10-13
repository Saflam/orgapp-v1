<?php

namespace Modules\Events\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Events\Enums\EventStatus;
use Modules\Events\Enums\EventVisibility;
use Modules\Events\Models\Event;
use Modules\Events\Models\EventParticipant;
use Modules\Events\Notifications\EventLifecycleNotification;
use Modules\Member\Models\Member;

class EventParticipantService
{
    public function queryForOrganization(Organization $organization, Event $event): Builder
    {
        $this->assertEventBelongsToOrganization($event, $organization);

        return EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id);
    }

    public function invite(
        Organization $organization,
        Event $event,
        int $inviterId,
        array $data
    ): EventParticipant {
        $this->assertEventBelongsToOrganization($event, $organization);

        if ($event->status !== EventStatus::Published) {
            throw ValidationException::withMessages([
                'event' => 'Participants can only be invited to published events.',
            ]);
        }

        if ($event->visibility !== EventVisibility::Restricted) {
            throw ValidationException::withMessages([
                'event' => 'Manual invitations are only available for restricted events.',
            ]);
        }

        $participant = DB::transaction(function () use ($organization, $event, $inviterId, $data): EventParticipant {
            $lockedEvent = Event::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($event->id);
            $this->assertRegistrationOpen($lockedEvent);
            $this->assertRegistrationCapacity($lockedEvent);
            $member = null;

            if ($data['participant_type'] === 'member') {
                $member = Member::query()
                    ->where('id', $data['member_id'])
                    ->where('organization_id', $organization->id)
                    ->first();

                if (! $member) {
                    throw ValidationException::withMessages([
                        'member_id' => 'The selected member does not belong to this organization.',
                    ]);
                }

                $alreadyInvited = EventParticipant::query()
                    ->where('event_id', $event->id)
                    ->where('member_id', $member->id)
                    ->exists();

                if ($alreadyInvited) {
                    throw ValidationException::withMessages([
                        'member_id' => 'This member is already registered or invited to the event.',
                    ]);
                }
            } else {
                $email = mb_strtolower(trim((string) $data['email']));
                $alreadyInvited = EventParticipant::query()
                    ->where('event_id', $event->id)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->exists();

                if ($alreadyInvited) {
                    throw ValidationException::withMessages([
                        'email' => 'This email address is already registered or invited to the event.',
                    ]);
                }

                $data['email'] = $email;
            }

            return EventParticipant::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'member_id' => $member?->id,
                'participant_type' => $data['participant_type'],
                'name' => $member ? null : trim((string) $data['name']),
                'email' => $member ? null : $data['email'],
                'phone' => $member ? null : ($data['phone'] ?? null),
                'status' => 'invited',
                'invited_by' => $inviterId,
                'invited_at' => now(),
            ])->load(['member.user', 'inviter']);
        });

        $this->notifyParticipant($event, $participant, 'invited');

        return $participant;
    }

    public function registerPublic(Organization $organization, Event $event, array $data): EventParticipant
    {
        $this->assertEventBelongsToOrganization($event, $organization);

        if ($event->status !== EventStatus::Published || $event->visibility !== EventVisibility::Public) {
            throw ValidationException::withMessages([
                'event' => 'Public registration is only available for published public events.',
            ]);
        }

        $email = mb_strtolower(trim((string) $data['email']));
        $customValues = $data['custom_fields'] ?? [];
        $participant = DB::transaction(function () use ($organization, $event, $data, $email, $customValues): EventParticipant {
            $lockedEvent = Event::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($event->id);
            $this->assertRegistrationOpen($lockedEvent);
            $this->assertRegistrationCapacity($lockedEvent);
            $duplicate = EventParticipant::query()->where('event_id', $lockedEvent->id)->whereRaw('LOWER(email) = ?', [$email])->exists();
            if ($duplicate) {
                throw ValidationException::withMessages(['email' => 'This email address is already registered for the event.']);
            }
            return EventParticipant::query()->create([
                'organization_id' => $organization->id,
                'event_id' => $event->id,
                'participant_type' => 'guest',
                'name' => trim((string) $data['name']),
                'email' => $email,
                'phone' => isset($data['phone']) ? trim((string) $data['phone']) : null,
                'status' => 'registered',
                'registered_at' => now(),
                'metadata' => ['custom_fields' => $customValues],
            ]);
        });

        $this->notifyParticipant($event, $participant, 'registered');

        return $participant;
    }

    public function cancel(Organization $organization, Event $event, EventParticipant $participant): EventParticipant
    {
        $this->assertEventBelongsToOrganization($event, $organization);

        if ((int) $participant->organization_id !== (int) $organization->id
            || (int) $participant->event_id !== (int) $event->id) {
            abort(404);
        }

        $wasCancelled = false;

        $cancelled = DB::transaction(function () use ($organization, $event, $participant, &$wasCancelled): EventParticipant {
            $lockedParticipant = EventParticipant::query()
                ->where('organization_id', $organization->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->findOrFail($participant->id);

            if ($lockedParticipant->status === 'cancelled') {
                return $lockedParticipant->refresh()->load('pass');
            }

            $wasCancelled = true;

            $pass = $lockedParticipant->pass()->lockForUpdate()->first();
            if ($pass && (int) $pass->current_inside > 0) {
                throw ValidationException::withMessages([
                    'participant' => 'A participant cannot be cancelled while people on their pass are inside the event.',
                ]);
            }

            if ($pass && $pass->status === 'active') {
                $pass->status = 'revoked';
                $pass->revoked_at = now();
                $pass->save();
            }

            $lockedParticipant->status = 'cancelled';
            $lockedParticipant->save();

            return $lockedParticipant->refresh()->load('pass');
        });

        if ($wasCancelled) {
            $this->notifyParticipant($event, $cancelled, 'participant_cancelled');
        }

        return $cancelled;
    }

    /** Mark an eligible participant as a no-show after the event has ended. */
    public function markNoShow(Organization $organization, Event $event, EventParticipant $participant): EventParticipant
    {
        $this->assertEventBelongsToOrganization($event, $organization);
        if ((int) $participant->organization_id !== (int) $organization->id
            || (int) $participant->event_id !== (int) $event->id) {
            abort(404);
        }

        if (! $event->ends_at || ! $event->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'participant' => 'A participant can only be marked as a no-show after the event has ended.',
            ]);
        }

        return DB::transaction(function () use ($organization, $event, $participant): EventParticipant {
            $lockedEvent = Event::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($event->id);
            $lockedParticipant = EventParticipant::query()
                ->where('organization_id', $organization->id)
                ->where('event_id', $lockedEvent->id)
                ->lockForUpdate()
                ->findOrFail($participant->id);

            if (in_array($lockedParticipant->status, ['cancelled', 'declined'], true)) {
                throw ValidationException::withMessages([
                    'participant' => 'Cancelled or declined participants cannot be marked as a no-show.',
                ]);
            }
            if ($lockedParticipant->status === 'no_show') {
                return $lockedParticipant;
            }

            $pass = $lockedParticipant->pass()->lockForUpdate()->first();
            $hasAttendance = $pass && $pass->attendanceScans()->exists();
            if ($hasAttendance || ($pass && (int) $pass->current_inside > 0)) {
                throw ValidationException::withMessages([
                    'participant' => 'A participant with recorded attendance cannot be marked as a no-show.',
                ]);
            }

            $lockedParticipant->status = 'no_show';
            $lockedParticipant->save();

            return $lockedParticipant->refresh()->load('pass');
        });
    }

    public function notifyEventCancellation(Organization $organization, Event $event): void
    {
        $this->assertEventBelongsToOrganization($event, $organization);

        EventParticipant::query()
            ->where('organization_id', $organization->id)
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->with('member.user')
            ->chunkById(100, function ($participants) use ($event): void {
                foreach ($participants as $participant) {
                    $this->notifyParticipant($event, $participant, 'event_cancelled');
                }
            });
    }

    private function assertRegistrationOpen(Event $event): void
    {
        $now = now($event->timezone ?: config('app.timezone'));
        if ($event->status !== EventStatus::Published || $event->ends_at?->isPast()) {
            throw ValidationException::withMessages(['event' => 'Registration is closed for this event.']);
        }
        if ($event->registration_opens_at && $now->lt($event->registration_opens_at)) {
            throw ValidationException::withMessages(['event' => 'Registration has not opened yet.']);
        }
        if ($event->registration_closes_at && $now->gt($event->registration_closes_at)) {
            throw ValidationException::withMessages(['event' => 'The registration cutoff has passed.']);
        }
    }

    private function assertRegistrationCapacity(Event $event): void
    {
        if ($event->registration_capacity === null) {
            return;
        }
        $count = EventParticipant::query()
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->whereNotIn('status', ['cancelled', 'declined'])
            ->count();
        if ($count >= $event->registration_capacity) {
            throw ValidationException::withMessages(['event' => 'The event registration capacity has been reached.']);
        }
    }

    private function notifyParticipant(Event $event, EventParticipant $participant, string $kind): void
    {
        $participant->loadMissing('member.user');
        $email = $participant->email ?: $participant->member?->user?->email;

        if (! $email) {
            return;
        }

        $name = $participant->name ?: $participant->member?->user?->name ?: 'Participant';

        try {
            Notification::route('mail', $email)->notify(
                new EventLifecycleNotification($event, $kind, $name)
            );
        } catch (\Throwable $exception) {
            // Delivery failure must not roll back a committed registration or cancellation.
            Log::error('Event participant notification delivery failed.', [
                'event_id' => $event->id,
                'participant_id' => $participant->id,
                'kind' => $kind,
                'exception' => $exception::class,
            ]);
        }
    }

    private function assertEventBelongsToOrganization(Event $event, Organization $organization): void
    {
        if ((int) $event->organization_id !== (int) $organization->id) {
            abort(404);
        }
    }
}
