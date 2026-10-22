<?php

namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\SubmitIar;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IarControlledSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function draft(User $creator): ImpactAssessment
    {
        return ImpactAssessment::create([
            'iar_number' => 'IAR-TEST-SUBMISSION-' . $creator->id,
            'ccr_number' => 'CCR-TEST',
            'creator_id' => $creator->id,
            'current_version' => 'Current',
            'new_version' => 'New',
            'sap_udi_icc_impacted' => false,
            'labelling_impacted' => false,
            'is_urgent' => false,
            'due_at' => now()->addDays(10),
            'status' => 'draft',
        ]);
    }

    public function test_empty_draft_is_blocked_and_no_assignments_or_audit_created(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $iar = $this->draft($creator);

        $this->actingAs($creator)->post(route('iar.submit', $iar))
            ->assertRedirect(route('iar.readiness', $iar))
            ->assertSessionHasErrors('submission');

        $this->assertSame('draft', $iar->fresh()->status);
        $this->assertDatabaseCount('iar_assignments', 0);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'iar.submitted')->count());
    }

    public function test_another_global_ra_cannot_submit_creators_draft(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('global-ra');
        $iar = $this->draft($creator);

        $this->actingAs($other)->post(route('iar.submit', $iar))->assertForbidden();
        $this->assertSame('draft', $iar->fresh()->status);
    }

    public function test_inactive_creator_cannot_submit(): void
    {
        $creator = User::factory()->create(['is_active' => false]);
        $creator->assignRole('global-ra');
        $iar = $this->draft($creator);
        $this->actingAs($creator)->post(route('iar.submit', $iar))->assertRedirect();
        $this->assertDatabaseCount('iar_assignments', 0);
    }

    public function test_get_request_cannot_submit(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $iar = $this->draft($creator);
        $this->actingAs($creator)->get('/iars/'.$iar->id.'/submit')->assertStatus(405);
        $this->assertSame('draft', $iar->fresh()->status);
    }

    public function test_ready_creator_strategy_submits_assigns_and_audits_once(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $iar = $this->draft($creator);

        $regionId = DB::table('regions')->insertGetId([
            'code' => 'TEST-REG', 'name' => 'Test Region',
            'assignment_strategy' => 'creator', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $countryId = DB::table('countries')->insertGetId([
            'region_id' => $regionId, 'iso2' => 'ZX', 'iso3' => 'ZXX',
            'name' => 'Test Country', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'sap_code' => 'SAP-TEST-5C2', 'product_name' => 'Test Product',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('product_registrations')->insert([
            'product_id' => $productId, 'country_id' => $countryId,
            'registration_status' => 'registered',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('iar_products')->insert([
            'impact_assessment_id' => $iar->id, 'product_id' => $productId,
            'submitted_sap_code' => 'SAP-TEST-5C2',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($creator)->post(route('iar.submit', $iar))
            ->assertRedirect(route('iar.show', $iar));

        $this->assertSame('submitted', $iar->fresh()->status);
        $this->assertNotNull($iar->fresh()->submitted_at);
        $this->assertDatabaseHas('iar_assignments', [
            'impact_assessment_id' => $iar->id,
            'country_id' => $countryId,
            'assigned_to_user_id' => $creator->id,
            'assignment_source' => 'creator',
            'status' => 'assigned',
        ]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'iar.submitted')->where('entity_id', $iar->id)->count());
        $this->assertDatabaseCount('notification_logs', 0);

        // A second POST must not create another country assignment or submission audit.
        $this->actingAs($creator)->post(route('iar.submit', $iar))
            ->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('iar_assignments', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'iar.submitted')->where('entity_id', $iar->id)->count());
    }

}
