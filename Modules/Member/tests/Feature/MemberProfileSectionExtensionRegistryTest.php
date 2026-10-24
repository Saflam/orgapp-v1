<?php

namespace Modules\Member\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Modules\Core\Services\ModuleRegistry;
use Modules\Core\Services\OrganizationModuleService;
use Modules\Member\Contracts\MemberProfileSectionExtension;
use Modules\Member\Models\Member;
use Modules\Member\Services\MemberProfileSectionExtensionRegistry;
use Tests\TestCase;

class MemberProfileSectionExtensionRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_extensions_for_enabled_organization_modules_are_included(): void
    {
        $this->mock(ModuleRegistry::class, function ($mock): void {
            $mock->shouldReceive('isAvailable')->withAnyArgs()->andReturn(true);
        });

        $organization = Organization::factory()->create();
        app(OrganizationModuleService::class)->enable($organization, 'Demo');

        $registry = app(MemberProfileSectionExtensionRegistry::class);
        $registry->register(new FakeMemberProfileSectionExtension());

        $sections = $registry->sections($organization, Member::factory()->create([
            'organization_id' => $organization->id,
        ]));

        $this->assertCount(1, $sections);
        $this->assertSame('Demo', $sections[0]['module']);
        $this->assertSame('Demo details', $sections[0]['title']);
        $this->assertSame('demo::profile-section', $sections[0]['view']);
        $this->assertSame(['message' => 'Visible'], $sections[0]['data']);
    }

    public function test_disabled_optional_module_contributes_no_profile_section(): void
    {
        $this->mock(ModuleRegistry::class, function ($mock): void {
            $mock->shouldReceive('isAvailable')->withAnyArgs()->andReturn(true);
        });

        $organization = Organization::factory()->create();
        $registry = app(MemberProfileSectionExtensionRegistry::class);
        $registry->register(new FakeMemberProfileSectionExtension());

        $sections = $registry->sections($organization, Member::factory()->create([
            'organization_id' => $organization->id,
        ]));

        $this->assertSame([], $sections);
    }
}

class FakeMemberProfileSectionExtension implements MemberProfileSectionExtension
{
    public function module(): string
    {
        return 'Demo';
    }

    public function title(): string
    {
        return 'Demo details';
    }

    public function view(): string
    {
        return 'demo::profile-section';
    }

    public function editView(): string
    {
        return 'demo::profile-section-edit';
    }

    public function data(Organization $organization, Member $member): ?array
    {
        return ['message' => 'Visible'];
    }

    public function update(\Illuminate\Http\Request $request, Organization $organization, Member $member): void
    {
        // This fake extension has no editable fields.
    }
}
