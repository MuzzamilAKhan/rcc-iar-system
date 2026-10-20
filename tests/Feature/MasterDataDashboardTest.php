<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    public function test_super_admin_can_open_master_data(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole('super-admin');

        $this->actingAs($user)
            ->get('/admin/master-data')
            ->assertOk()
            ->assertSee('Master Data Management');
    }

    public function test_local_ra_cannot_open_master_data(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole('local-ra');

        $this->actingAs($user)
            ->get('/admin/master-data')
            ->assertForbidden();
    }

    public function test_guest_cannot_open_master_data(): void
    {
        $this->get('/admin/master-data')
            ->assertRedirect('/login');
    }
}
