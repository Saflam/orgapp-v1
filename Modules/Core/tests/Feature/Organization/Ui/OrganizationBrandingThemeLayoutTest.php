<?php

namespace Modules\Core\Tests\Feature\Organization\Ui;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Organization;
use Modules\Core\Services\OrganizationSettingsService;
use Tests\TestCase;

class OrganizationBrandingThemeLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_layout_displays_organization_logo_favicon_and_saved_dark_theme(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create([
            'is_system_admin' => true,
        ]);

        $organization = Organization::factory()->create([
            'name' => 'Brand Test Org',
        ]);

        $logoPath = 'organizations/' . $organization->id . '/branding/logo.png';
        $faviconPath = 'organizations/' . $organization->id . '/branding/favicon.png';

        Storage::disk('public')->put($logoPath, 'logo');
        Storage::disk('public')->put($faviconPath, 'favicon');

        $settings = app(OrganizationSettingsService::class);

        $settings->set($organization, 'branding.logo', $logoPath);
        $settings->set($organization, 'branding.favicon', $faviconPath);
        $settings->set($organization, 'ui.theme', 'dark');

        $this->actingAs($admin);

        $response = response()->view('core::layouts.organization-admin', [
            'slot' => '<p>Branding test content</p>',
            'pageTitle' => 'Brand Test Org',
            'layoutOrganization' => $organization,
        ]);

        $this->assertSame(200, $response->getStatusCode());

        $html = $response->getContent();

        $this->assertIsString($html);
        $this->assertStringContainsString('data-theme="dark"', $html);
        $this->assertStringContainsString('rel="icon"', $html);
        $this->assertStringContainsString('Brand Test Org logo', $html);
        $this->assertStringContainsString($logoPath, $html);
        $this->assertStringContainsString($faviconPath, $html);
    }
}
