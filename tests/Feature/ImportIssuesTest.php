<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportIssuesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    public function test_admin_can_view_import_issues(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
        ]);

        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/import-issues')
            ->assertOk()
            ->assertSee('Import Issues');
    }

    public function test_global_ra_can_view_import_issues(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        $this->actingAs($user)->get('/admin/import-issues')->assertOk()->assertSee('Import Issues');
        $this->actingAs($user)->get('/admin/products')->assertForbidden();
    }

    public function test_master_data_manager_retains_access(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('master-data-manager');
        $this->actingAs($user)->get('/admin/import-issues')->assertOk();
    }

    public function test_inactive_global_ra_cannot_view_import_issues(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('global-ra');

        $this->actingAs($user)
            ->get('/admin/import-issues')
            ->assertRedirect();
    }

    public function test_local_ra_cannot_view_import_issues(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole('local-ra');

        $this->actingAs($user)
            ->get('/admin/import-issues')
            ->assertForbidden();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get('/admin/import-issues')
            ->assertRedirect('/login');
    }
}
