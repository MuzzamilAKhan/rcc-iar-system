<?php
namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\ResolveDeferredCountry;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IarDeferredResolutionTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function fixture(): array
    {
        $creator = User::factory()->create(['is_active'=>true]); $creator->assignRole('global-ra');
        $assessor = User::factory()->create(['is_active'=>true, 'provisioning_status'=>'approved']);
        $assessor->assignRole('local-ra');
        $iar = ImpactAssessment::create([
            'iar_number'=>'IAR-RESOLVE-001','ccr_number'=>'CCR-RESOLVE', 'creator_id'=>$creator->id,
            'current_version'=>'Before','new_version'=>'After','sap_udi_icc_impacted'=>false,
            'labelling_impacted'=>false,'is_urgent'=>false,'due_at'=>now()->addDays(9),
            'submitted_at'=>now()->subDays(5),'status'=>'submitted',
        ]);
        $region = DB::table('regions')->insertGetId(['code'=>'6C2-R','name'=>'Deferred region',
            'assignment_strategy'=>'country_assessor','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $country = DB::table('countries')->insertGetId(['region_id'=>$region,'iso2'=>'QH','iso3'=>'QHH',
            'name'=>'Deferred country','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $exception = DB::table('assignment_exceptions')->insertGetId([
            'impact_assessment_id'=>$iar->id,'country_id'=>$country,'exception_type'=>'assessor_unavailable',
            'resolution_status'=>'open','details'=>'Primary country assessor mapping is missing.',
            'created_at'=>now(),'updated_at'=>now()]);
        return [$creator,$assessor,$iar,$country,$exception];
    }

    private function mapAssessor(User $assessor, int $country): void
    {
        DB::table('country_assessors')->insert(['country_id'=>$country,'user_id'=>$assessor->id,
            'assignment_priority'=>1,'is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
    }

    public function test_pending_country_requires_valid_mapping_and_preserves_original_due_date(): void
    {
        [$creator,$assessor,$iar,$country,$exception] = $this->fixture();
        $this->actingAs($creator)->get(route('iar.deferred.index',$iar))->assertOk()->assertSee('Deferred country');
        $this->actingAs($creator)->post(route('iar.deferred.resolve',[$iar,$exception]))->assertSessionHasErrors('resolution');
        $this->assertDatabaseMissing('iar_assignments',['impact_assessment_id'=>$iar->id,'country_id'=>$country]);
        $this->mapAssessor($assessor,$country);
        $this->actingAs($creator)->post(route('iar.deferred.resolve',[$iar,$exception]))
            ->assertRedirect(route('iar.deferred.index',$iar));
        $this->assertDatabaseHas('assignment_exceptions',['id'=>$exception,'resolution_status'=>'resolved']);
        $assignment = DB::table('iar_assignments')->where('impact_assessment_id',$iar->id)->where('country_id',$country)->first();
        $this->assertNotNull($assignment);
        $this->assertSame((int)$assessor->id,(int)$assignment->assigned_to_user_id);
        $this->assertSame((string)$iar->fresh()->due_at->format('Y-m-d H:i:s'),(string)$assignment->due_at);
        $this->assertDatabaseHas('audit_logs',['entity_id'=>$iar->id,'action'=>'iar.country_assignment.resolved']);
        $this->actingAs($creator)->post(route('iar.deferred.resolve',[$iar,$exception]))->assertSessionHasErrors('resolution');
        $this->assertSame(1,DB::table('iar_assignments')->where('impact_assessment_id',$iar->id)->count());
    }

    public function test_unrelated_user_cannot_resolve_pending_country(): void
    {
        [$creator,$assessor,$iar,$country,$exception] = $this->fixture();
        $this->mapAssessor($assessor,$country);
        $other = User::factory()->create(['is_active'=>true]); $other->assignRole('global-ra');
        $this->actingAs($other)->get(route('iar.deferred.index',$iar))->assertForbidden();
        $this->actingAs($other)->post(route('iar.deferred.resolve',[$iar,$exception]))->assertForbidden();
        $this->assertDatabaseMissing('iar_assignments',['impact_assessment_id'=>$iar->id,'country_id'=>$country]);
    }

    public function test_inactive_primary_assessor_remains_unassigned(): void
    {
        [$creator,$assessor,$iar,$country,$exception] = $this->fixture();
        $this->mapAssessor($assessor,$country);
        $assessor->update(['is_active'=>false]);
        $this->actingAs($creator)->post(route('iar.deferred.resolve',[$iar,$exception]))->assertSessionHasErrors('resolution');
        $this->assertDatabaseHas('assignment_exceptions',['id'=>$exception,'resolution_status'=>'open']);
        $this->assertDatabaseCount('iar_assignments',0);
    }
}
