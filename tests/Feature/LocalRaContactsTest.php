<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalRaContactsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    public function test_super_admin_can_view_local_ra(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
        ]);

        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/local-ra')
            ->assertOk()
            ->assertSee('Local RA Country Assessors');
    }

    public function test_local_ra_cannot_view_admin_mapping(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole('local-ra');

        $this->actingAs($user)
            ->get('/admin/local-ra')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_local_ra(): void
    {
        $this->get('/admin/local-ra')
            ->assertRedirect('/login');
    }
}
