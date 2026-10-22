<?php

namespace Modules\Member\Rules;

use Illuminate\Contracts\Validation\Rule;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;

/**
 * Validates a phone number using Google's libphonenumber metadata.
 *
 * If the value is national-format, a calling code is required. International
 * values beginning with "+" can be validated without a separate calling code.
 */
class ValidPhoneNumber implements Rule
{
    public function __construct(
        private readonly ?string $callingCode = null,
    ) {
    }

    public function passes($attribute, $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $value = trim($value);
        $callingCode = $this->normalizeCallingCode($this->callingCode);

        try {
            if (str_starts_with($value, '+')) {
                $number = $phoneUtil->parse($value, null);
            } else {
                if ($callingCode === null) {
                    return false;
                }

                $region = $phoneUtil->getRegionCodeForCountryCode(
                    (int) $callingCode
                );

                if (! is_string($region) || $region === '') {
                    return false;
                }

                $number = $phoneUtil->parse($value, $region);
            }

            if (! $phoneUtil->isValidNumber($number)) {
                return false;
            }

            // If a calling code was supplied, it must agree with an
            // international number supplied by the client.
            if (
                $callingCode !== null
                && (string) $number->getCountryCode() !== $callingCode
            ) {
                return false;
            }

            return true;
        } catch (NumberParseException) {
            return false;
        }
    }

    public function message(): string
    {
        return 'The :attribute must be a valid phone number for the selected country calling code, or a valid international number beginning with +.';
    }

    private function normalizeCallingCode(?string $callingCode): ?string
    {
        if ($callingCode === null || trim($callingCode) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $callingCode);

        return is_string($digits) && $digits !== ''
            ? $digits
            : null;
    }
}
