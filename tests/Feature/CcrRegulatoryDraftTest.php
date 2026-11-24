<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrRegulatoryDraftTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function fixture(string $iso = 'IT'): array
    {
        $owner = User::factory()->create(['is_active'=>true]); $owner->assignRole('global-ra');
        $assessor = User::factory()->create(['is_active'=>true]); $assessor->assignRole('local-ra');
        $other = User::factory()->create(['is_active'=>true]); $other->assignRole('local-ra');
        $now=now('UTC');
        $region=DB::table('regions')->insertGetId(['code'=>'REG'.uniqid(),'name'=>'Synthetic region','created_at'=>$now,'updated_at'=>$now]);
        $country=DB::table('countries')->insertGetId(['region_id'=>$region,'iso2'=>$iso,'iso3'=>$iso==='GB'?'GBR':'ITA',
            'name'=>$iso==='GB'?'United Kingdom':'Italy','created_at'=>$now,'updated_at'=>$now]);
        $product=DB::table('products')->insertGetId(['sap_code'=>'SYNTH-CCR4B','product_name'=>'Synthetic device','created_at'=>$now,'updated_at'=>$now]);
        $registration=DB::table('product_registrations')->insertGetId(['product_id'=>$product,'country_id'=>$country,
            'registration_status'=>'unknown','created_at'=>$now,'updated_at'=>$now]);
        $ccr=DB::table('change_control_records')->insertGetId(['ccr_number'=>'CCR-4B-'.uniqid(),'created_by'=>$owner->id,
            'title'=>'Synthetic material change','change_type'=>'materials','current_state'=>'A','proposed_state'=>'B',
            'reason_for_change'=>'Synthetic test','change_duration'=>'permanent','status'=>'draft','created_at'=>$now,'updated_at'=>$now]);
        DB::table('ccr_products')->insert(['change_control_record_id'=>$ccr,'product_id'=>$product,'created_at'=>$now,'updated_at'=>$now]);
        $screen=DB::table('ccr_regulatory_market_screenings')->insertGetId(['change_control_record_id'=>$ccr,'country_id'=>$country,
            'jurisdiction_hint'=>$iso==='GB'?'uk_jurisdiction_review':'local_market_review','status'=>'pending_regulatory_screening',
            'created_by_user_id'=>$owner->id,'created_at'=>$now,'updated_at'=>$now]);
        DB::table('ccr_regulatory_market_sources')->insert(['ccr_regulatory_market_screening_id'=>$screen,
            'product_registration_id'=>$registration,'product_id'=>$product,'source_registration_status'=>'unknown',
            'created_at'=>$now,'updated_at'=>$now]);
        return [$owner,$assessor,$other,$ccr,$screen];
    }

    private function payload(): array { return ['jurisdiction_route'=>'eu_eea_review','preliminary_outcome'=>'additional_information_required',
        'rationale'=>'The supplier change needs a reviewed regulatory analysis for this market.',
        'evidence_references'=>'TEST-SPEC Rev 1; TEST-WI Rev 2']; }

    public function test_owner_assigns_active_ra_only_once_and_assessor_gets_scoped_queue(): void
    {
        Mail::fake(); [$owner,$assessor,$other,$ccr,$screen]=$this->fixture();
        $this->actingAs($other)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$other->id])->assertForbidden();
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$assessor->id])->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$assessor->id])->assertRedirect();
        $this->assertDatabaseCount('ccr_regulatory_drafts',1);
        $this->actingAs($other)->get(route('ccr.regulatory-drafts.my'))->assertDontSee('Synthetic material change');
        $this->actingAs($assessor)->get(route('ccr.regulatory-drafts.my'))->assertSee('Synthetic material change');
        $this->assertSame(1,DB::table('audit_logs')->where('entity_id',$ccr)->where('action','ccr.regulatory.assigned')->count());
        Mail::assertNothingOutgoing();
    }

    public function test_only_assignee_can_edit_audited_idempotent_draft_without_clearance(): void
    {
        [$owner,$assessor,$other,$ccr,$screen]=$this->fixture();
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$assessor->id]);
        $this->actingAs($other)->get(route('ccr.regulatory-drafts.edit',$screen))->assertForbidden();
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.save',$screen),$this->payload())->assertForbidden();
        $this->actingAs($assessor)->get(route('ccr.regulatory-drafts.edit',$screen))->assertOk()->assertSee('Source registration evidence');
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),$this->payload())->assertRedirect();
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),$this->payload())->assertRedirect();
        $this->assertDatabaseHas('ccr_regulatory_drafts',['screening_id'=>$screen,'status'=>'draft', 'jurisdiction_route'=>'eu_eea_review']);
        $this->assertDatabaseHas('ccr_regulatory_market_screenings',['id'=>$screen,'status'=>'pending_regulatory_screening']);
        $this->assertSame(1,DB::table('audit_logs')->where('entity_id',$ccr)->where('action','ccr.regulatory.draft.saved')->count());
    }

    public function test_unknown_uk_jurisdiction_must_stay_unresolved(): void
    {
        [$owner,$assessor,,$ccr,$screen]=$this->fixture('GB');
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$assessor->id]);
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),$this->payload())->assertStatus(422);
        $data=$this->payload();$data['jurisdiction_route']='unresolved';
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),$data)->assertRedirect();
        $this->assertDatabaseHas('ccr_regulatory_drafts',['screening_id'=>$screen,'jurisdiction_route'=>'unresolved']);
    }

    public function test_locked_ccr_invalid_assessor_and_invalid_draft_are_blocked(): void
    {
        [$owner,$assessor,,$ccr,$screen]=$this->fixture();
        $inactive=User::factory()->create(['is_active'=>false]);$inactive->assignRole('local-ra');
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$inactive->id])->assertStatus(422);
        $this->actingAs($owner)->post(route('ccr.regulatory-drafts.assign',$screen),['assigned_to_user_id'=>$assessor->id])->assertRedirect();
        $data=$this->payload();$data['rationale']='short';
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),$data)->assertSessionHasErrors('rationale');
        DB::table('change_control_records')->where('id',$ccr)->update(['status'=>'pending_approval']);
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),$this->payload())->assertStatus(409);
        $this->assertDatabaseHas('ccr_regulatory_drafts',['screening_id'=>$screen,'status'=>'assigned']);
    }
}
