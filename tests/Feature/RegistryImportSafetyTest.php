<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Product;
use App\Models\Region;
use App\Models\User;
use App\Services\RegistryImport\RegistryImportParser;
use App\Services\RegistryImport\RegistryImportWorkflow;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistryImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    private User $uploader;
    private User $reviewer;
    private int $countryId;
    private int $productA;
    private int $productB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $region = Region::create(['code' => 'SAFETY-EU', 'name' => 'Safety Test Europe']);
        $country = Country::create(['region_id' => $region->id, 'iso2' => 'DE', 'iso3' => 'DEU', 'name' => 'Germany']);
        $this->countryId = $country->id;
        $this->productA = Product::create(['sap_code' => 'SAFETY-001', 'product_name' => 'Synthetic Safety One'])->id;
        $this->productB = Product::create(['sap_code' => 'SAFETY-002', 'product_name' => 'Synthetic Safety Two'])->id;
        $this->uploader = User::factory()->create(['is_active' => true]);
        $this->uploader->assignRole('master-data-manager');
        $this->reviewer = User::factory()->create(['is_active' => true]);
        $this->reviewer->assignRole('super-admin');
    }

    private function registration(int $product, string $number): int
    {
        return DB::table('product_registrations')->insertGetId([
            'product_id' => $product,
            'country_id' => $this->countryId,
            'registration_status' => 'active',
            'registration_number' => $number,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function submit(array $entries): int
    {
        $path = tempnam(sys_get_temp_dir(), 'reg-safety-');
        $csv = "SAP Code,Country of Registration,Registration Status,Registration Number\n";
        foreach ($entries as $entry) {
            $csv .= implode(',', $entry) . "\n";
        }
        file_put_contents($path, $csv);
        $file = new UploadedFile($path, 'synthetic-safety.csv', 'text/csv', null, true);
        try {
            return app(RegistryImportWorkflow::class)->submit(
                $this->uploader,
                $file,
                'Controlled synthetic import safety test',
                app(RegistryImportParser::class)
            );
        } finally {
            @unlink($path);
        }
    }

    private function approve(int $id): void
    {
        app(RegistryImportWorkflow::class)->decide(
            $this->reviewer, $id, 'approve', 'Independent safety approval test reason'
        );
    }

    public function test_changed_existing_registration_blocks_entire_batch_without_partial_writes(): void
    {
        $this->registration($this->productA, 'ORIGINAL-A');
        $this->registration($this->productB, 'ORIGINAL-B');
        $id = $this->submit([
            ['SAFETY-001', 'Germany', 'active', 'NEW-A'],
            ['SAFETY-002', 'Germany', 'active', 'NEW-B'],
        ]);
        DB::table('product_registrations')->where('product_id', $this->productB)
            ->update(['registration_number' => 'CHANGED-OUTSIDE']);

        try {
            $this->approve($id);
            $this->fail('A stale import must be rejected.');
        } catch (ValidationException $expected) {
            $this->assertDatabaseHas('registry_import_requests', ['id' => $id, 'status' => 'pending']);
            $this->assertDatabaseHas('product_registrations', ['product_id' => $this->productA, 'registration_number' => 'ORIGINAL-A']);
            $this->assertDatabaseHas('product_registrations', ['product_id' => $this->productB, 'registration_number' => 'CHANGED-OUTSIDE']);
            $this->assertSame(0, DB::table('audit_logs')->where('action', 'product_registry.registration.import_applied')->count());
        }
    }

    public function test_new_registration_conflict_prevents_all_other_changes(): void
    {
        $this->registration($this->productA, 'ORIGINAL-A');
        $id = $this->submit([
            ['SAFETY-001', 'Germany', 'active', 'NEW-A'],
            ['SAFETY-002', 'Germany', 'active', 'NEW-B'],
        ]);
        $this->registration($this->productB, 'CONCURRENT-B');
        $this->expectException(ValidationException::class);
        try {
            $this->approve($id);
        } finally {
            $this->assertDatabaseHas('registry_import_requests', ['id' => $id, 'status' => 'pending']);
            $this->assertDatabaseHas('product_registrations', ['product_id' => $this->productA, 'registration_number' => 'ORIGINAL-A']);
            $this->assertDatabaseHas('product_registrations', ['product_id' => $this->productB, 'registration_number' => 'CONCURRENT-B']);
        }
    }

    public function test_second_approval_is_rejected_and_does_not_repeat_audits(): void
    {
        $id = $this->submit([['SAFETY-001', 'Germany', 'active', 'APPROVED-ONCE']]);
        $this->approve($id);
        $auditCount = DB::table('audit_logs')->where('action', 'product_registry.registration.import_applied')->count();
        $this->assertSame(1, $auditCount);
        try {
            $this->approve($id);
            $this->fail('Duplicate approval must not succeed.');
        } catch (ValidationException $expected) {
            $this->assertDatabaseHas('registry_import_requests', ['id' => $id, 'status' => 'approved']);
            $this->assertDatabaseCount('product_registrations', 1);
            $this->assertSame($auditCount, DB::table('audit_logs')->where('action', 'product_registry.registration.import_applied')->count());
        }
    }

    public function test_rejected_request_cannot_later_be_approved(): void
    {
        $id = $this->submit([['SAFETY-001', 'Germany', 'active', 'MUST-NOT-APPLY']]);
        app(RegistryImportWorkflow::class)->decide($this->reviewer, $id, 'reject', 'Rejected during synthetic validation');
        try {
            $this->approve($id);
            $this->fail('Rejected request must remain immutable.');
        } catch (ValidationException $expected) {
            $this->assertDatabaseHas('registry_import_requests', ['id' => $id, 'status' => 'rejected']);
            $this->assertDatabaseCount('product_registrations', 0);
        }
    }

    public function test_local_ra_cannot_approve_and_uploader_cannot_approve(): void
    {
        $id = $this->submit([['SAFETY-001', 'Germany', 'active', 'NOT-APPROVED']]);
        $local = User::factory()->create(['is_active' => true]);
        $local->assignRole('local-ra');
        $this->actingAs($local)->post(route('admin.products.import.requests.decide', $id), [
            'decision' => 'approve', 'review_reason' => 'Unauthorized attempt for safety test',
        ])->assertForbidden();
        $this->actingAs($this->uploader)->post(route('admin.products.import.requests.decide', $id), [
            'decision' => 'approve', 'review_reason' => 'Unauthorized uploader approval attempt',
        ])->assertForbidden();
        $this->assertDatabaseHas('registry_import_requests', ['id' => $id, 'status' => 'pending']);
        $this->assertDatabaseCount('product_registrations', 0);
    }
}
