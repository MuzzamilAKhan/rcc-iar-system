<?php

namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\IarSubmissionReadiness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IarPartialExceptionSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function setupDraft(): array
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $iar = ImpactAssessment::create([
            'iar_number' => 'IAR-6A-TEST', 'ccr_number' => 'CCR-6A',
            'creator_id' => $creator->id, 'current_version' => 'Before',
            'new_version' => 'After', 'sap_udi_icc_impacted' => false,
            'labelling_impacted' => false, 'is_urgent' => false,
            'due_at' => now()->addDays(30), 'status' => 'draft',
        ]);
        $region = DB::table('regions')->insertGetId([
            'code'=>'R6A', 'name'=>'Region 6A', 'assignment_strategy'=>'creator',
            'is_active'=>1, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $country = DB::table('countries')->insertGetId([
            'region_id'=>$region, 'iso2'=>'QZ', 'iso3'=>'QZZ',
            'name'=>'Country 6A', 'is_active'=>1, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $registered = DB::table('products')->insertGetId([
            'sap_code'=>'REG-6A', 'product_name'=>'Registered sample',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $unregistered = DB::table('products')->insertGetId([
            'sap_code'=>'UNREG-6A', 'product_name'=>'Unregistered sample',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        DB::table('product_registrations')->insert([
            'product_id'=>$registered, 'country_id'=>$country, 'registration_status'=>'registered',
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
        foreach ([[$registered,'REG-6A'], [$unregistered,'UNREG-6A'], [null,'MISSING-6A']] as [$productId,$code]) {
            DB::table('iar_products')->insert([
                'impact_assessment_id'=>$iar->id, 'product_id'=>$productId,
                'submitted_sap_code'=>$code, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
        return [$creator,$iar,$country];
    }

    private function recordJustification(ImpactAssessment $iar, string $reason): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id'=>$iar->creator_id, 'entity_type'=>ImpactAssessment::class,
            'entity_id'=>$iar->id, 'action'=>'iar.sap.override.created',
            'new_values'=>json_encode(['unresolved_sap_codes'=>['MISSING-6A'], 'reason'=>$reason]),
            'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public function test_registered_country_proceeds_and_both_sap_exceptions_are_preserved(): void
    {
        [$creator,$iar,$country] = $this->setupDraft();
        $this->recordJustification($iar, 'Approved synthetic exception rationale for missing SAP.');
        $report = app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertTrue($report['can_submit']);
        $this->assertSame(['MISSING-6A'], $report['unresolved_sap_codes']);
        $this->assertSame(['UNREG-6A'], $report['unregistered_sap_codes']);
        $this->actingAs($creator)->get(route('iar.readiness', $iar))
            ->assertOk()->assertSee('MISSING-6A')->assertSee('UNREG-6A');
        $this->actingAs($creator)->post(route('iar.submit', $iar))->assertRedirect(route('iar.show', $iar));
        $this->assertSame('submitted', $iar->fresh()->status);
        $this->assertDatabaseHas('iar_assignments', [
            'impact_assessment_id'=>$iar->id, 'country_id'=>$country,
        ]);
        $this->assertSame(1, DB::table('iar_assignments')->where('impact_assessment_id',$iar->id)->count());
        $this->assertDatabaseHas('assignment_exceptions', [
            'impact_assessment_id'=>$iar->id,'sap_code'=>'MISSING-6A','exception_type'=>'unresolved_sap',
        ]);
        $this->assertDatabaseHas('assignment_exceptions', [
            'impact_assessment_id'=>$iar->id,'sap_code'=>'UNREG-6A','exception_type'=>'no_country_registration',
        ]);
        $this->assertSame(2, DB::table('assignment_exceptions')->where('impact_assessment_id',$iar->id)->count());
        $this->actingAs($creator)->post(route('iar.submit',$iar))->assertSessionHasErrors('submission');
        $this->assertSame(2, DB::table('assignment_exceptions')->where('impact_assessment_id',$iar->id)->count());
        $this->assertDatabaseCount('notification_logs',0);
    }

    public function test_missing_justification_still_blocks_submission(): void
    {
        [$creator,$iar] = $this->setupDraft();
        $report=app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertFalse($report['can_submit']);
        $this->actingAs($creator)->post(route('iar.submit',$iar))->assertSessionHasErrors('submission');
        $this->assertDatabaseCount('assignment_exceptions',0);
        $this->assertDatabaseCount('iar_assignments',0);
    }

    public function test_exception_only_draft_stays_blocked_pending_client_policy(): void
    {
        [$creator,$iar] = $this->setupDraft();
        $this->recordJustification($iar, 'Approved synthetic exception rationale for missing SAP.');
        DB::table('iar_products')->where('impact_assessment_id',$iar->id)
            ->where('submitted_sap_code','REG-6A')->delete();
        $report=app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertFalse($report['can_submit']);
        $this->actingAs($creator)->post(route('iar.submit',$iar))->assertSessionHasErrors('submission');
        $this->assertSame('draft',$iar->fresh()->status);
    }
}
