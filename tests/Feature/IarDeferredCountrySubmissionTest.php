<?php
namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\IarSubmissionReadiness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IarDeferredCountrySubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function setupScenario(bool $withReady = true): array
    {
        $creator=User::factory()->create(['is_active'=>true]); $creator->assignRole('global-ra');
        $iar=ImpactAssessment::create([
            'iar_number'=>'IAR-6C-TEST','ccr_number'=>'CCR-6C','creator_id'=>$creator->id,
            'current_version'=>'Before','new_version'=>'After', 'sap_udi_icc_impacted'=>false,
            'labelling_impacted'=>false,'is_urgent'=>false,'due_at'=>now()->addDays(30),'status'=>'draft',
        ]);
        $readyRegion=DB::table('regions')->insertGetId(['code'=>'6C-R','name'=>'Ready region',
            'assignment_strategy'=>'creator','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $pendingRegion=DB::table('regions')->insertGetId(['code'=>'6C-P','name'=>'Pending region',
            'assignment_strategy'=>'country_assessor','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $countryReady=DB::table('countries')->insertGetId(['region_id'=>$readyRegion,'iso2'=>'QW','iso3'=>'QWW',
            'name'=>'Ready country','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $countryPending=DB::table('countries')->insertGetId(['region_id'=>$pendingRegion,'iso2'=>'QE','iso3'=>'QEE',
            'name'=>'Pending country','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        $product=DB::table('products')->insertGetId(['sap_code'=>'SAP-6C','product_name'=>'Synthetic product',
            'created_at'=>now(),'updated_at'=>now()]);
        foreach ([$countryPending, ...($withReady?[$countryReady]:[])] as $country) {
            DB::table('product_registrations')->insert(['product_id'=>$product,'country_id'=>$country,
                'registration_status'=>'registered','created_at'=>now(),'updated_at'=>now()]);
        }
        DB::table('iar_products')->insert(['impact_assessment_id'=>$iar->id,'product_id'=>$product,
            'submitted_sap_code'=>'SAP-6C','created_at'=>now(),'updated_at'=>now()]);
        return [$creator,$iar,$countryReady,$countryPending];
    }

    public function test_ready_country_submits_and_unmapped_country_remains_audited_pending(): void
    {
        [$creator,$iar,$ready,$pending]=$this->setupScenario();
        $r=app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertTrue($r['can_submit']); $this->assertSame(1,$r['ready_count']);
        $this->assertSame(1,$r['deferred_count']);
        $this->actingAs($creator)->get(route('iar.readiness',$iar))->assertOk()->assertSee('Pending assessor assignment');
        $this->actingAs($creator)->post(route('iar.submit',$iar))->assertRedirect(route('iar.show',$iar));
        $this->assertSame('submitted',$iar->fresh()->status);
        $this->assertDatabaseHas('iar_assignments',['impact_assessment_id'=>$iar->id,'country_id'=>$ready]);
        $this->assertDatabaseMissing('iar_assignments',['impact_assessment_id'=>$iar->id,'country_id'=>$pending]);
        $this->assertDatabaseHas('assignment_exceptions',['impact_assessment_id'=>$iar->id,
            'country_id'=>$pending,'exception_type'=>'assessor_unavailable','resolution_status'=>'open']);
        $this->assertSame(1,DB::table('iar_assignments')->where('impact_assessment_id',$iar->id)->count());
        $this->actingAs($creator)->post(route('iar.submit',$iar))->assertSessionHasErrors('submission');
        $this->assertSame(1,DB::table('assignment_exceptions')->where('impact_assessment_id',$iar->id)->count());
    }

    public function test_zero_eligible_countries_remains_draft(): void
    {
        [$creator,$iar]=$this->setupScenario(false);
        $r=app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertFalse($r['can_submit']);
        $this->actingAs($creator)->post(route('iar.submit',$iar))->assertSessionHasErrors('submission');
        $this->assertSame('draft',$iar->fresh()->status);
        $this->assertDatabaseCount('assignment_exceptions',0);
    }
}
