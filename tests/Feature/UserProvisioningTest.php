<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    public function test_super_admin_can_view_users(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Account Provisioning');
    }

    public function test_master_data_manager_cannot_view_users(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('master-data-manager');

        $this->actingAs($manager)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_users(): void
    {
        $this->get('/admin/users')
            ->assertRedirect('/login');
    }
}
