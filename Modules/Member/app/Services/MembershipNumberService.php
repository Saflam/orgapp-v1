<?php

namespace Modules\Member\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Services\MembershipNumberingSettingsService;
use Modules\Member\Models\Member;

/** Allocates organization-scoped membership numbers in three-letter series. */
class MembershipNumberService
{
    public function suggest(Organization $organization): string
    {
        return DB::transaction(function () use ($organization): string {
            $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $numbering = $this->numberingSettings($lockedOrganization);

            return sprintf('%s%s-%04d', $numbering['base_prefix'], $numbering['series'], $numbering['next_sequence']);
        });
    }

    /** Allocate and persist the next number. Call inside the confirmation transaction. */
    public function allocate(Organization $organization): string
    {
        $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
        $settings = $lockedOrganization->settings ?? [];
        $numbering = $this->numberingSettings($lockedOrganization);
        $number = sprintf('%s%s-%04d', $numbering['base_prefix'], $numbering['series'], $numbering['next_sequence']);

        if (Member::query()->where('organization_id', $lockedOrganization->id)->where('membership_number', $number)->exists()) {
            // Repair stale counters by advancing until a free number is found.
            do {
                $numbering = $this->advance($numbering);
                $number = sprintf('%s%s-%04d', $numbering['base_prefix'], $numbering['series'], $numbering['next_sequence']);
            } while (Member::query()->where('organization_id', $lockedOrganization->id)->where('membership_number', $number)->exists());
        }

        $next = $this->advance($numbering);
        $settings[MembershipNumberingSettingsService::SETTING_KEY] = $next;
        $lockedOrganization->forceFill(['settings' => $settings])->save();

        return $number;
    }

    /**
     * Normalize an administrator-entered membership number.
     * A numeric value such as "5" becomes SAA-0005 (using this organization's
     * configured base prefix and current alphabetic series). Fully formatted
     * values are accepted only when they use this organization's prefix.
     */
    public function normalizeManualNumber(Organization $organization, string $membershipNumber): string
    {
        $value = strtoupper(trim($membershipNumber));
        if ($value === '') {
            return '';
        }

        $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
        $numbering = $this->numberingSettings($lockedOrganization);

        if (preg_match('/^[0-9]+$/', $value)) {
            $sequence = (int) $value;
            if ($sequence < 1 || $sequence > 9999) {
                throw ValidationException::withMessages([
                    'membership_number' => 'Enter a sequence between 1 and 9999, or leave the field empty for automatic assignment.',
                ]);
            }

            return sprintf('%s%s-%04d', $numbering['base_prefix'], $numbering['series'], $sequence);
        }

        if (! preg_match('/^([A-Z]{2})([A-Z])-([0-9]{4})$/', $value, $matches)
            || $matches[1] !== $numbering['base_prefix']
            || (int) $matches[3] < 1) {
            throw ValidationException::withMessages([
                'membership_number' => 'Enter a number from 1 to 9999 or a membership ID in the configured format, such as SAA-0005.',
            ]);
        }

        return $value;
    }

    /** Keep the persisted counter ahead of a manually assigned number in this series. */
    public function observeManualNumber(Organization $organization, string $membershipNumber): void
    {
        if (! preg_match('/^([A-Z]{2})([A-Z])-([0-9]{4})$/', strtoupper($membershipNumber), $matches)) {
            return;
        }

        $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
        $settings = $lockedOrganization->settings ?? [];
        $numbering = $this->numberingSettings($lockedOrganization);

        if ($matches[1] !== $numbering['base_prefix']) {
            return;
        }

        $usedSeries = ord($matches[2]) - ord('A');
        $currentSeries = ord($numbering['series']) - ord('A');
        $usedSequence = (int) $matches[3];
        if ($usedSeries > $currentSeries || ($usedSeries === $currentSeries && $usedSequence >= (int) $numbering['next_sequence'])) {
            $numbering['series'] = $matches[2];
            $numbering['next_sequence'] = $usedSequence + 1;
            if ($numbering['next_sequence'] > 9999) {
                $numbering = $this->advance($numbering);
            }
            $settings[MembershipNumberingSettingsService::SETTING_KEY] = $numbering;
            $lockedOrganization->forceFill(['settings' => $settings])->save();
        }
    }

    private function numberingSettings(Organization $organization): array
    {
        $settings = $organization->settings ?? [];
        $numbering = $settings[MembershipNumberingSettingsService::SETTING_KEY] ?? null;

        if (! is_array($numbering) || ! preg_match('/^[A-Z]{2}$/', (string) ($numbering['base_prefix'] ?? ''))) {
            $numbering = app(MembershipNumberingSettingsService::class)->initialize($organization);
        }

        return $numbering;
    }

    private function advance(array $numbering): array
    {
        if ((int) $numbering['next_sequence'] < 9999) {
            $numbering['next_sequence'] = (int) $numbering['next_sequence'] + 1;
            return $numbering;
        }

        if ($numbering['series'] === 'Z') {
            throw ValidationException::withMessages([
                'membership_number' => 'All membership-number series for this organization have been exhausted.',
            ]);
        }

        $numbering['series'] = chr(ord($numbering['series']) + 1);
        $numbering['next_sequence'] = 1;

        return $numbering;
    }
}
