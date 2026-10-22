<?php

namespace Modules\Core\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Organization;

/**
 * Initializes the immutable two-letter base used by an organization's
 * membership-number series. The third letter is managed by the Member module.
 */
class MembershipNumberingSettingsService
{
    public const SETTING_KEY = 'membership_numbering';

    public function initialize(Organization $organization, ?string $requestedPrefix = null): array
    {
        $prefix = $requestedPrefix === null || trim($requestedPrefix) === ''
            ? $this->suggestPrefix((string) $organization->name)
            : strtoupper(trim($requestedPrefix));

        if (! preg_match('/^[A-Z]{2}$/', $prefix)) {
            throw ValidationException::withMessages([
                'organization.membership_prefix' => 'The membership prefix must contain exactly two letters.',
            ]);
        }

        $settings = $organization->settings ?? [];
        $settings[self::SETTING_KEY] = [
            'base_prefix' => $prefix,
            'series' => 'A',
            'next_sequence' => 1,
            'initialized' => true,
        ];

        $organization->forceFill(['settings' => $settings])->save();

        return $settings[self::SETTING_KEY];
    }

    public function suggestPrefix(string $organizationName): string
    {
        $words = preg_split('/[^A-Za-z]+/', trim($organizationName), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = strtoupper(preg_replace('/[^A-Za-z]/', '', $organizationName) ?? '');

        if (strlen($letters) < 2) {
            throw ValidationException::withMessages([
                'organization.name' => 'The organization name must contain at least two letters to suggest a membership prefix.',
            ]);
        }

        // For multi-word names, use the initials of the first two words
        // (Kerala Cultural Association -> KC). For a single-word name, use
        // its first two letters (Saradhi -> SA). The creator can set a more
        // recognizable two-letter code, such as SD, during creation.
        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }

        return substr($letters, 0, 2);
    }
}
