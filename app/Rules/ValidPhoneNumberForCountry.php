<?php

namespace App\Rules;

use App\Support\PhoneNumbers\PhoneNumberNormalizer;
use Illuminate\Contracts\Validation\Rule;
use InvalidArgumentException;

class ValidPhoneNumberForCountry implements Rule
{
    public function __construct(private readonly ?string $countryIso2)
    {
    }

    public function passes($attribute, $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            app(PhoneNumberNormalizer::class)->normalize($value, $this->countryIso2);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function message(): string
    {
        return 'The :attribute must be a valid phone number for the selected country.';
    }
}
