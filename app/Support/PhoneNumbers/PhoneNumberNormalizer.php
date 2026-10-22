<?php

namespace App\Support\PhoneNumbers;

use InvalidArgumentException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class PhoneNumberNormalizer
{
    /**
     * Parse and validate a phone number, returning canonical storage data.
     *
     * National-format numbers require a selected ISO 3166-1 alpha-2 region.
     * International numbers may start with "+" and are parsed independently.
     *
     * @return array{
     *     phone_e164: string,
     *     country_iso2: ?string,
     *     country_calling_code: string,
     *     national_number: string,
     *     extension: ?string
     * }
     */
    public function normalize(string $value, ?string $countryIso2 = null): array
    {
        $value = trim($value);
        $countryIso2 = $countryIso2 !== null
            ? strtoupper(trim($countryIso2))
            : null;

        if ($value === '') {
            throw new InvalidArgumentException('A phone number is required.');
        }

        if ($countryIso2 !== null && ! preg_match('/^[A-Z]{2}$/', $countryIso2)) {
            throw new InvalidArgumentException('The selected phone country must be a two-letter ISO country code.');
        }

        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            if (str_starts_with($value, '+')) {
                $number = $phoneUtil->parse($value, null);
            } else {
                if ($countryIso2 === null) {
                    throw new InvalidArgumentException(
                        'Select a country when entering a phone number without an international calling code.'
                    );
                }

                $number = $phoneUtil->parse($value, $countryIso2);
            }
        } catch (NumberParseException $exception) {
            throw new InvalidArgumentException('The phone number could not be parsed.', previous: $exception);
        }

        if (! $phoneUtil->isValidNumber($number)) {
            throw new InvalidArgumentException('The phone number is not valid.');
        }

        $detectedRegion = $phoneUtil->getRegionCodeForNumber($number);
        if (
            $countryIso2 !== null
            && is_string($detectedRegion)
            && $detectedRegion !== ''
            && $detectedRegion !== '001'
            && $detectedRegion !== $countryIso2
        ) {
            throw new InvalidArgumentException('The phone number does not match the selected country.');
        }

        $storedRegion = is_string($detectedRegion)
            && $detectedRegion !== ''
            && $detectedRegion !== '001'
                ? $detectedRegion
                : $countryIso2;

        return [
            'phone_e164' => $phoneUtil->format($number, PhoneNumberFormat::E164),
            'country_iso2' => $storedRegion,
            'country_calling_code' => (string) $number->getCountryCode(),
            'national_number' => (string) $number->getNationalNumber(),
            'extension' => $number->hasExtension() ? $number->getExtension() : null,
        ];
    }
}
