<?php

namespace Database\Seeders;

use App\Services\Import\ClientMasterDataImporter;
use Illuminate\Database\Seeder;

final class ClientSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, ReferenceDataSeeder::class]);
        $statistics = app(ClientMasterDataImporter::class)->run();
        $this->command?->info('Client master data import: '.json_encode($statistics));
        $this->command?->warn('Imported contacts are inactive until separately provisioned. Review master_import_issues before issuing IARs.');
    }
}
