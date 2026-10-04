<?php

namespace Modules\Core\Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\IdentificationType;
use Tests\TestCase;

class IdentificationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_an_identification_type(): void
    {
        $identificationType = IdentificationType::create([
            'code' => 'civil_id',
            'name' => 'Civil ID',
            'description' => 'National civil identification number.',
        ]);

        $this->assertDatabaseHas('identification_types', [
            'id' => $identificationType->id,
            'code' => 'civil_id',
            'name' => 'Civil ID',
        ]);
    }

    public function test_identification_type_code_must_be_unique(): void
    {
        IdentificationType::create([
            'code' => 'passport',
            'name' => 'Passport',
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        IdentificationType::create([
            'code' => 'passport',
            'name' => 'Another Passport',
        ]);
    }
}