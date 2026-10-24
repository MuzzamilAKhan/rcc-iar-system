<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ProductRegistryExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_local_ra_cannot_export_registry(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('local-ra');
        $this->actingAs($user)->get(route('admin.products.export', ['scope' => 'all']))->assertForbidden();
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'product_registry.exported')->count());
    }

    public function test_authorized_export_has_missing_registration_row_and_audit(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('master-data-manager');
        Product::query()->create(['sap_code' => '00012345', 'product_name' => 'Synthetic test product']);

        $response = $this->actingAs($user)->get(route('admin.products.export', ['scope' => 'all']));
        $response->assertOk();
        $response->assertDownload();
        $file = $response->baseResponse->getFile()->getPathname();
        $book = IOFactory::load($file);
        try {
            $this->assertSame('00012345', $book->getActiveSheet()->getCell('A2')->getValue());
            $this->assertSame('Missing Registration', $book->getActiveSheet()->getCell('F2')->getValue());
        } finally {
            $book->disconnectWorksheets();
            @unlink($file);
        }
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'product_registry.exported')->count());
    }
}
