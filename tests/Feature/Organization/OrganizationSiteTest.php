<?php

namespace Tests\Feature\Organization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class OrganizationSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.deployment_mode' => 'single']);
    }

    public function test_organization_site_renders_the_resolved_organization_and_branding(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Saradhi Community',
            'settings' => [
                'branding' => [
                    'tagline' => 'Growing together.',
                    'primary_color' => '#123456',
                    'accent_color' => '#abcdef',
                ],
            ],
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $response = $this->get('/site');

        $response->assertOk()
            ->assertSee('Saradhi Community')
            ->assertSee('Growing together.')
            ->assertSee('#123456')
            ->assertSee(route('organization.manifest'), false);
    }

    public function test_organization_manifest_uses_the_current_organization_name_and_theme(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Saradhi Community',
            'settings' => ['branding' => ['primary_color' => '#123456']],
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $response = $this->get('/organization.webmanifest');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->assertJsonPath('name', 'Saradhi Community')
            ->assertJsonPath('start_url', '/site')
            ->assertJsonPath('theme_color', '#123456');
    }

    public function test_organization_branding_rejects_invalid_color_values(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['branding' => ['primary_color' => 'red; background:url(x)', 'accent_color' => 'not-a-color']],
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $response = $this->get('/site');

        $response->assertOk()
            ->assertSee('#174b3d')
            ->assertSee('#d9a441');
    }
}
