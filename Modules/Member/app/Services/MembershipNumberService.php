<?php

namespace Modules\Member\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;
use Modules\Core\Services\MembershipNumberingSettingsService;
use Modules\Member\Models\Member;

/** Allocates organization-scoped membership numbers in a three-letter series. */
class MembershipNumberService
{
    public function suggest(Organization $organization): string
    {
        return DB::transaction(function () use ($organization): string {
            $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
            $numbering = $this->numberingSettings($lockedOrganization);
            $number = $this->formatNumber($numbering);

            // Imports and historical/manual assignments can leave the persisted
            // counter behind. Suggestions must never advertise an occupied family ID.
            while (Member::query()
                ->where('organization_id', $lockedOrganization->id)
                ->where('membership_number', $number)
                ->exists()) {
                $numbering = $this->advance($numbering);
                $number = $this->formatNumber($numbering);
            }

            return $number;
        });
    }

    /** Allocate and persist the next number. Call inside the confirmation transaction. */
    public function allocate(Organization $organization): string
    {
        $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
        $settings = $lockedOrganization->settings ?? [];
        $numbering = $this->numberingSettings($lockedOrganization);
        $number = $this->formatNumber($numbering);

        if (Member::query()->where('organization_id', $lockedOrganization->id)->where('membership_number', $number)->exists()) {
            // Repair stale counters by advancing until a free number is found.
            do {
                $numbering = $this->advance($numbering);
                $number = $this->formatNumber($numbering);
            } while (Member::query()->where('organization_id', $lockedOrganization->id)->where('membership_number', $number)->exists());
        }

        $next = $this->advance($numbering);
        $settings[MembershipNumberingSettingsService::SETTING_KEY] = $next;
        $lockedOrganization->forceFill(['settings' => $settings])->save();

        return $number;
    }

    /**
     * Normalize an administrator-entered membership number.
     * Numeric values are formatted as SAA-0005 up to 9999, then SAA-10,000.
     * Commas in numeric input are optional. Fully formatted values must use
     * this organization's configured prefix and series.
     */
    public function normalizeManualNumber(Organization $organization, string $membershipNumber): string
    {
        $value = strtoupper(trim($membershipNumber));
        if ($value === '') {
            return '';
        }

        $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
        $numbering = $this->numberingSettings($lockedOrganization);

        if (preg_match('/^(?:[0-9]+|[0-9]{1,3}(?:,[0-9]{3})+)$/', $value)) {
            $sequence = $this->parseSequence($value);
            if ($sequence < 1) {
                throw ValidationException::withMessages([
                    'membership_number' => 'Enter a positive membership number, or leave the field empty for automatic assignment.',
                ]);
            }

            return $this->formatSequence($numbering, $sequence);
        }

        $prefix = preg_quote($numbering['base_prefix'], '/');
        $series = preg_quote($numbering['series'], '/');
        if (! preg_match('/^'.$prefix.$series.'-([0-9]{1,3}(?:,[0-9]{3})*|[0-9]+)$/', $value, $matches)) {
            throw ValidationException::withMessages([
                'membership_number' => 'Enter a positive number or an ID in the configured format, such as SAA-0005 or SAA-10,000.',
            ]);
        }

        $sequence = $this->parseSequence($matches[1]);
        if ($sequence < 1) {
            throw ValidationException::withMessages([
                'membership_number' => 'Membership number must be greater than zero.',
            ]);
        }

        return $this->formatSequence($numbering, $sequence);
    }

    /** Keep the persisted counter ahead of a manually assigned or imported number. */
    public function observeManualNumber(Organization $organization, string $membershipNumber): void
    {
        $value = strtoupper(trim($membershipNumber));
        if (! preg_match('/^([A-Z]{2})([A-Z])-([0-9]{1,3}(?:,[0-9]{3})*|[0-9]+)$/', $value, $matches)) {
            return;
        }

        $lockedOrganization = Organization::query()->whereKey($organization->id)->lockForUpdate()->firstOrFail();
        $settings = $lockedOrganization->settings ?? [];
        $numbering = $this->numberingSettings($lockedOrganization);

        if ($matches[1] !== $numbering['base_prefix'] || $matches[2] !== $numbering['series']) {
            return;
        }

        $usedSequence = $this->parseSequence($matches[3]);
        if ($usedSequence >= (int) $numbering['next_sequence']) {
            $numbering['next_sequence'] = $usedSequence + 1;
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
        // Saradhi keeps the same alphabetic series after 9999:
        // SAA-9999 is followed by SAA-10,000, not SAB-0001.
        $numbering['next_sequence'] = (int) $numbering['next_sequence'] + 1;

        return $numbering;
    }

    private function formatNumber(array $numbering): string
    {
        return $this->formatSequence($numbering, (int) $numbering['next_sequence']);
    }

    private function formatSequence(array $numbering, int $sequence): string
    {
        $formatted = $sequence <= 9999
            ? str_pad((string) $sequence, 4, '0', STR_PAD_LEFT)
            : number_format($sequence, 0, '.', ',');

        return $numbering['base_prefix'].$numbering['series'].'-'.$formatted;
    }

    private function parseSequence(string $value): int
    {
        return (int) str_replace(',', '', $value);
    }
}
