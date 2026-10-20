<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalRaContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    public function test_super_admin_can_view_contacts(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
        ]);

        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/global-ra')
            ->assertOk()
            ->assertSee('Global RA Contacts');
    }

    public function test_local_ra_cannot_view_admin_contacts(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole('local-ra');

        $this->actingAs($user)
            ->get('/admin/global-ra')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/global-ra')
            ->assertRedirect('/login');
    }
}
