<?php

namespace Modules\Member\Database\Factories;

use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Unit;
use Modules\Member\Models\Member;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'unit_id' => null,
            'membership_number' => 'MEM-' . fake()->unique()->numerify('######'),
            'joined_at' => fake()->optional()->date(),
            'metadata' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Member $member): void {
            OrganizationMembership::firstOrCreate(
                [
                    'organization_id' => $member->organization_id,
                    'user_id' => $member->user_id,
                ],
                [
                    'status' => 'active',
                ],
            );
        });
    }

    public function forOrganization(Organization|int $organization): static
    {
        return $this->state([
            'organization_id' => $organization instanceof Organization
                ? $organization->id
                : $organization,
            'unit_id' => null,
        ]);
    }

    public function forUnit(Unit $unit): static
    {
        return $this->state([
            'organization_id' => $unit->organization_id,
            'unit_id' => $unit->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }
}