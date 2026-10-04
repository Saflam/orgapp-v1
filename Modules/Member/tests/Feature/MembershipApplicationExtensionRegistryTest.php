<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\ModuleRegistry;
use Modules\Member\Contracts\MembershipApplicationExtension;
use Modules\Member\Models\Member;
use Modules\Member\Services\MembershipApplicationExtensionRegistry;
use Tests\TestCase;

class MembershipApplicationExtensionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_an_extension(): void
    {
        $organization = $this->createEnabledOrganization('Fake');

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $extension = new FakeMembershipApplicationExtension();

        $registry->register($extension);

        $this->assertArrayHasKey(
            'Fake',
            $registry->enabledFor($organization)
        );
    }

    public function test_disabled_extension_is_not_enabled_for_an_organization(): void
    {
        $organization = Organization::factory()->create();

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $registry->register(
            new FakeMembershipApplicationExtension()
        );

        $this->assertArrayNotHasKey(
            'Fake',
            $registry->enabledFor($organization)
        );
    }

    public function test_enabled_extension_is_returned_for_an_organization(): void
    {
        $organization = $this->createEnabledOrganization('Fake');

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $registry->register(
            new FakeMembershipApplicationExtension()
        );

        $extensions = $registry->enabledFor($organization);

        $this->assertArrayHasKey('Fake', $extensions);

        $this->assertInstanceOf(
            FakeMembershipApplicationExtension::class,
            $extensions['Fake']
        );
    }

    public function test_it_returns_fields_only_for_enabled_extensions(): void
    {
        $organization = $this->createEnabledOrganization('Fake');

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $registry->register(
            new FakeMembershipApplicationExtension()
        );

        $this->assertSame(
            [
                'Fake' => [
                    'example_field' => [
                        'type' => 'text',
                    ],
                ],
            ],
            $registry->fields($organization)
        );
    }

    public function test_it_returns_rules_only_for_enabled_extensions(): void
    {
        $organization = $this->createEnabledOrganization('Fake');

        $registry = app(
            MembershipApplicationExtensionRegistry::class
        );

        $registry->register(
            new FakeMembershipApplicationExtension()
        );

        $this->assertSame(
            [
                'Fake' => [
                    'example_field' => [
                        'nullable',
                        'string',
                    ],
                ],
            ],
            $registry->rules($organization)
        );
    }

    private function createEnabledOrganization(
        string $module,
    ): Organization {
        $this->mock(
            ModuleRegistry::class,
            function ($mock) use ($module): void {
                $mock->shouldReceive('isAvailable')
                    ->andReturnUsing(
                        fn (string $requestedModule): bool =>
                            $requestedModule === $module
                    );
            }
        );

        $organization = Organization::factory()->create();

        app(
            \Modules\Core\Services\OrganizationModuleService::class
        )->enable(
            $organization,
            $module,
        );

        return $organization;
    }
}

class FakeMembershipApplicationExtension
    implements MembershipApplicationExtension
{
    public function module(): string
    {
        return 'Fake';
    }

    public function fields(): array
    {
        return [
            'example_field' => [
                'type' => 'text',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            'example_field' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function persist(
        Organization $organization,
        Member $member,
        array $data,
    ): void {
        //
    }
}