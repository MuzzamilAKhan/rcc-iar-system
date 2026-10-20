<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Import\ClientMasterDataImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClientSampleDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_import_is_idempotent_and_does_not_activate_contact_accounts(): void
    {
        $root = storage_path('app/private/iar-import');
        if (! file_exists($root.'/mat_rows.csv')) {
            $this->markTestSkipped('Place private sample import files under storage/app/private/iar-import.');
        }
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $this->seed(\Database\Seeders\ReferenceDataSeeder::class);

        $importer = app(ClientMasterDataImporter::class);
        $first = $importer->run();
        $this->assertGreaterThan(0, $first['mat']);
        $this->assertGreaterThan(0, $first['local']);
        $this->assertGreaterThan(0, $first['global']);
        $this->assertGreaterThan(0, DB::table('master_import_issues')->count());
        $counts = [
            User::count(), DB::table('products')->count(),
            DB::table('product_registrations')->count(),
            DB::table('country_assessors')->count(),
        ];
        $importer->run();
        $this->assertSame($counts, [
            User::count(), DB::table('products')->count(),
            DB::table('product_registrations')->count(),
            DB::table('country_assessors')->count(),
        ]);
        $this->assertSame(0, User::where('is_active', true)->count());
    }
}
