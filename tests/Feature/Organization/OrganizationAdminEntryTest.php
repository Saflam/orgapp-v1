<?php

namespace Tests\Feature\Organization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Organization;
use Tests\TestCase;

class OrganizationAdminEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_visiting_organization_admin_is_redirected_to_login(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'is_active' => true,
        ]);

        $this->get('http://org1.lvh.me:8000/admin')
            ->assertRedirect('/login');
    }


    public function test_authenticated_user_does_not_see_sign_in_on_organization_homepage(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'name' => 'Organization One',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($user)
            ->get('http://org1.lvh.me:8000/home')
            ->assertOk()
            ->assertDontSee('>Sign in</a>', false)
            ->assertSee('>Logout</button>', false);
    }

    public function test_authenticated_visit_to_login_uses_role_aware_destination(): void
    {
        $user = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/admin');
    }


    public function test_authenticated_system_admin_on_tenant_host_is_redirected_to_organization_settings(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'name' => 'Organization One',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_system_admin' => true]);

        $this->actingAs($user)
            ->get('http://org1.lvh.me:8000/login')
            ->assertRedirect('/organization/settings');
    }

    public function test_non_member_profile_uses_authenticated_header_and_apply_membership_action(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        Organization::factory()->create([
            'slug' => 'org1',
            'name' => 'Organization One',
            'is_active' => true,
        ]);

        $user = User::factory()->create(['is_system_admin' => false]);

        $this->actingAs($user)
            ->get('http://org1.lvh.me:8000/profile')
            ->assertOk()
            ->assertDontSee('>Sign in</a>', false)
            ->assertSee('>View Profile</a>', false)
            ->assertSee('Apply Membership')
            ->assertDontSee('Member Profile');
    }

    public function test_logout_ends_the_session_and_redirects_to_the_public_site(): void
    {
        config([
            'app.deployment_mode' => 'multi',
            'app.organization_base_domain' => 'lvh.me',
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }
}
