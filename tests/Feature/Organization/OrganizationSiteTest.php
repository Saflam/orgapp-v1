<?php

namespace Tests\Feature\Organization;

use App\Models\User;
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
                    'logo_path' => '/storage/organizations/saradhi/logo.png',
                    'favicon_path' => '/storage/organizations/saradhi/favicon.png',
                ],
            ],
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $response = $this->get('/home');

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Saradhi Community')
            ->assertSee('Growing together.')
            ->assertSee('#123456')
            ->assertSee('src="/storage/organizations/saradhi/logo.png"', false)
            ->assertSee('<link rel="icon" href="/storage/organizations/saradhi/favicon.png">', false)
            ->assertSee(route('organization.manifest'), false);
    }

    public function test_non_member_organization_admin_sees_apply_membership_and_account_dropdown_not_member_profile(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Saradhi Community',
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $admin = User::factory()->create([
            'name' => 'Saradhi Administrator',
            'email' => 'admin@saradhi.test',
            'is_system_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/home')
            ->assertOk()
            ->assertSee('Manage Organization')
            ->assertSee('Apply Membership')
            ->assertSee('Saradhi Administrator')
            ->assertSee('admin@saradhi.test')
            ->assertSee('View Profile')
            ->assertSee('Logout')
            ->assertDontSee('>Member Profile</a>', false);
    }

    public function test_authenticated_non_member_can_open_general_profile_page(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Saradhi Community',
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $admin = User::factory()->create([
            'name' => 'Saradhi Administrator',
            'email' => 'admin@saradhi.test',
            'is_system_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/profile')
            ->assertOk()
            ->assertSee('Your Profile')
            ->assertSee('Saradhi Administrator')
            ->assertSee('Apply Membership');
    }

    public function test_organization_manifest_uses_the_current_organization_name_theme_and_favicon(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Saradhi Community',
            'settings' => [
                'branding' => [
                    'primary_color' => '#123456',
                    'favicon_path' => '/storage/organizations/saradhi/favicon.png',
                ],
            ],
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $response = $this->get('/organization.webmanifest');

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('name', 'Saradhi Community')
            ->assertJsonPath('start_url', '/home')
            ->assertJsonPath('theme_color', '#123456')
            ->assertJsonPath('icons.0.src', '/storage/organizations/saradhi/favicon.png');
    }

    public function test_organization_branding_rejects_invalid_color_and_asset_paths(): void
    {
        $organization = Organization::factory()->create([
            'settings' => ['branding' => [
                'primary_color' => 'red; background:url(x)',
                'accent_color' => 'not-a-color',
                'logo_path' => 'https://example.com/logo.png',
                'favicon_path' => '/storage/../private/favicon.png',
            ]],
        ]);
        config(['app.default_organization_id' => $organization->id]);

        $this->get('/home')
            ->assertOk()
            ->assertSee('#174b3d')
            ->assertSee('#d9a441')
            ->assertDontSee('https://example.com/logo.png')
            ->assertSee('<link rel="icon" href="/favicon.ico">', false);

        $this->get('/organization.webmanifest')
            ->assertOk()
            ->assertJsonPath('icons.0.src', '/favicon.ico');
    }

    public function test_organization_site_and_manifest_are_resolved_per_tenant_host(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $orgOne = Organization::factory()->create([
            'name' => 'Organization One',
            'slug' => 'org1',
            'is_active' => true,
            'settings' => ['branding' => ['primary_color' => '#112233']],
        ]);
        Organization::factory()->create([
            'name' => 'Organization Two',
            'slug' => 'org2',
            'is_active' => true,
            'settings' => ['branding' => ['primary_color' => '#445566']],
        ]);

        $this->get('http://org1.lvh.me/home')
            ->assertOk()
            ->assertSee('Organization One')
            ->assertSee('#112233')
            ->assertDontSee('Organization Two');

        $this->get('http://org1.lvh.me/organization.webmanifest')
            ->assertOk()
            ->assertJsonPath('name', 'Organization One')
            ->assertJsonPath('theme_color', '#112233');

        $this->get('http://unknown.lvh.me/home')->assertNotFound();
        $this->get('http://unknown.lvh.me/organization.webmanifest')->assertNotFound();
    }
}
