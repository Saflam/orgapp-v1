<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Support\Facades\Validator;
use Modules\Member\Rules\ValidPhoneNumber;
use Tests\TestCase;

class ValidPhoneNumberTest extends TestCase
{
    public function test_valid_national_number_is_accepted_with_its_calling_code(): void
    {
        $validator = Validator::make(
            ['whatsapp' => '771234567'],
            ['whatsapp' => ['required', new ValidPhoneNumber('+94')]],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_invalid_number_is_rejected_with_its_calling_code(): void
    {
        $validator = Validator::make(
            ['whatsapp' => '123'],
            ['whatsapp' => ['required', new ValidPhoneNumber('+94')]],
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('whatsapp', $validator->errors()->toArray());
    }

    public function test_valid_international_number_is_accepted_without_a_separate_calling_code(): void
    {
        $validator = Validator::make(
            ['whatsapp' => '+919876543210'],
            ['whatsapp' => ['required', new ValidPhoneNumber()]],
        );

        $this->assertFalse($validator->fails());
    }

    public function test_national_number_without_a_calling_code_is_rejected(): void
    {
        $validator = Validator::make(
            ['whatsapp' => '9876543210'],
            ['whatsapp' => ['required', new ValidPhoneNumber()]],
        );

        $this->assertTrue($validator->fails());
    }

    public function test_international_number_must_match_the_supplied_calling_code(): void
    {
        $validator = Validator::make(
            ['whatsapp' => '+919876543210'],
            ['whatsapp' => ['required', new ValidPhoneNumber('+94')]],
        );

        $this->assertTrue($validator->fails());
    }
}
