<?php
namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductRegistryImportPreviewTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }
    public function test_manager_can_access_preview_and_local_ra_cannot(): void
    {
        $manager=User::factory()->create(['is_active'=>true]); $manager->assignRole('master-data-manager');
        $local=User::factory()->create(['is_active'=>true]); $local->assignRole('local-ra');
        $this->actingAs($manager)->get(route('admin.products.import.preview'))->assertOk()->assertSee('PREVIEW FIRST');
        $this->actingAs($local)->get(route('admin.products.import.preview'))->assertForbidden();
    }
}
