<?php

namespace Modules\Member\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Member\Enums\MembershipApplicationStatus;
use Modules\Member\Models\MembershipApplication;

class MembershipApplicationEditService
{
    public function update(
        MembershipApplication $application,
        User $actor,
        array $changes,
    ): MembershipApplication {
        if (! in_array($application->status, [
            MembershipApplicationStatus::SUBMITTED,
            MembershipApplicationStatus::VERIFIED,
            MembershipApplicationStatus::REVIEWED,
        ], true)) {
            throw ValidationException::withMessages([
                'status' => 'Application data can only be edited during verification and review.',
            ]);
        }

        return DB::transaction(function () use ($application, $actor, $changes): MembershipApplication {
            $before = $application->data ?? [];
            $after = $before;

            foreach (['member', 'address', 'extension_data'] as $section) {
                if (! array_key_exists($section, $changes)) {
                    continue;
                }

                $existingSection = is_array($after[$section] ?? null)
                    ? $after[$section]
                    : [];
                $changedSection = is_array($changes[$section])
                    ? $changes[$section]
                    : [];

                if ($section !== 'extension_data') {
                    $after[$section] = array_replace($existingSection, $changedSection);
                    continue;
                }

                foreach ($changedSection as $module => $moduleData) {
                    $existingModuleData = is_array($existingSection[$module] ?? null)
                        ? $existingSection[$module]
                        : [];

                    $existingSection[$module] = is_array($moduleData)
                        ? array_replace($existingModuleData, $moduleData)
                        : $moduleData;
                }

                $after[$section] = $existingSection;
            }

            $application->update(['data' => $after]);

            $application->changes()->create([
                'actor_user_id' => $actor->id,
                'reason' => $changes['reason'] ?? null,
                'before_data' => $before,
                'after_data' => $after,
            ]);

            return $application->refresh();
        });
    }
}
