<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrRegulatoryReviewTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }
    private function fixture(string $registrationStatus='active'): array
    {
        $owner=User::factory()->create(['is_active'=>true]);$owner->assignRole('global-ra');
        $assessor=User::factory()->create(['is_active'=>true]);$assessor->assignRole('local-ra');
        $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('super-admin');
        $other=User::factory()->create(['is_active'=>true]);$other->assignRole('local-ra');
        $now=now('UTC');
        $region=DB::table('regions')->insertGetId(['code'=>'T'.uniqid(),'name'=>'Synthetic region','created_at'=>$now,'updated_at'=>$now]);
        $country=DB::table('countries')->insertGetId(['region_id'=>$region,'iso2'=>'IT','iso3'=>'ITA','name'=>'Italy','created_at'=>$now,'updated_at'=>$now]);
        $product=DB::table('products')->insertGetId(['sap_code'=>'T-4C','product_name'=>'Synthetic device','created_at'=>$now,'updated_at'=>$now]);
        $reg=DB::table('product_registrations')->insertGetId(['product_id'=>$product,'country_id'=>$country,'registration_status'=>$registrationStatus,'created_at'=>$now,'updated_at'=>$now]);
        $ccr=DB::table('change_control_records')->insertGetId(['ccr_number'=>'CCR-4C-'.uniqid(),'created_by'=>$owner->id,'title'=>'Synthetic CCR',
            'change_type'=>'materials','current_state'=>'A','proposed_state'=>'B','reason_for_change'=>'Testing',
            'change_duration'=>'permanent','status'=>'draft','created_at'=>$now,'updated_at'=>$now]);
        DB::table('ccr_products')->insert(['change_control_record_id'=>$ccr,'product_id'=>$product,'created_at'=>$now,'updated_at'=>$now]);
        $screen=DB::table('ccr_regulatory_market_screenings')->insertGetId(['change_control_record_id'=>$ccr,'country_id'=>$country,
            'jurisdiction_hint'=>'local_market_review','status'=>'pending_regulatory_screening','created_by_user_id'=>$owner->id,'created_at'=>$now,'updated_at'=>$now]);
        DB::table('ccr_regulatory_market_sources')->insert(['ccr_regulatory_market_screening_id'=>$screen,'product_registration_id'=>$reg,
            'product_id'=>$product,'source_registration_status'=>$registrationStatus,'created_at'=>$now,'updated_at'=>$now]);
        DB::table('ccr_regulatory_drafts')->insert(['screening_id'=>$screen,'assigned_to_user_id'=>$assessor->id,'assigned_by_user_id'=>$owner->id,
            'status'=>'draft','jurisdiction_route'=>'eu_eea_review','preliminary_outcome'=>'potential_notification',
            'rationale'=>'Synthetic potential authority notification to be evaluated by human RA.',
            'evidence_references'=>'TEST-SOP-01','assigned_at'=>$now,'created_at'=>$now,'updated_at'=>$now]);
        return [$owner,$assessor,$admin,$other,$ccr,$screen];
    }
    public function test_assignee_submission_locks_and_independent_admin_can_accept_only_when_resolved(): void
    {
        Mail::fake();[$owner,$assessor,$admin,$other,$ccr,$screen]=$this->fixture();
        $this->actingAs($other)->post(route('ccr.regulatory-reviews.submit',$screen))->assertForbidden();
        $this->actingAs($assessor)->post(route('ccr.regulatory-reviews.submit',$screen))->assertRedirect();
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),['jurisdiction_route'=>'eu_eea_review',
            'preliminary_outcome'=>'potential_notification','rationale'=>'Any sufficiently long changed rationale.'])->assertStatus(409);
        $this->actingAs($assessor)->post(route('ccr.regulatory-reviews.submit',$screen))->assertStatus(409);
        $this->actingAs($other)->get(route('ccr.regulatory-reviews.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('ccr.regulatory-reviews.index'))->assertSee('Italy');
        $this->actingAs($admin)->post(route('ccr.regulatory-reviews.decide',$screen),['decision'=>'reviewed'])->assertRedirect();
        $this->assertDatabaseHas('ccr_regulatory_drafts',['screening_id'=>$screen,'status'=>'reviewed']);
        $this->assertDatabaseHas('ccr_regulatory_market_screenings',['id'=>$screen,'status'=>'pending_regulatory_screening']);
        $this->assertDatabaseHas('change_control_records',['id'=>$ccr,'status'=>'draft']);
        Mail::assertNothingOutgoing();
    }
    public function test_return_requires_comment_and_assignee_can_revise_and_resubmit(): void
    {
        [$owner,$assessor,$admin,,$ccr,$screen]=$this->fixture();
        $this->actingAs($assessor)->post(route('ccr.regulatory-reviews.submit',$screen))->assertRedirect();
        $this->actingAs($admin)->post(route('ccr.regulatory-reviews.decide',$screen),['decision'=>'returned','review_comment'=>'too short'])->assertSessionHasErrors('review_comment');
        $this->actingAs($admin)->post(route('ccr.regulatory-reviews.decide',$screen),['decision'=>'returned','review_comment'=>'Additional evidence is required.'])->assertRedirect();
        $this->actingAs($assessor)->get(route('ccr.regulatory-drafts.edit',$screen))->assertSee('Additional evidence is required.');
        $this->actingAs($assessor)->post(route('ccr.regulatory-drafts.save',$screen),['jurisdiction_route'=>'eu_eea_review',
            'preliminary_outcome'=>'potential_notification','rationale'=>'Revised rationale with documented evidence for notification.','evidence_references'=>'TEST-REV2'])->assertRedirect();
        $this->actingAs($assessor)->post(route('ccr.regulatory-reviews.submit',$screen))->assertRedirect();
        $this->assertDatabaseHas('ccr_regulatory_drafts',['screening_id'=>$screen,'status'=>'submitted']);
        $this->assertSame(2,DB::table('audit_logs')->where('entity_id',$ccr)->where('action','ccr.regulatory.submitted')->count());
    }
    public function test_unknown_registry_status_blocks_acceptance_and_readiness(): void
    {
        [$owner,$assessor,$admin,,$ccr,$screen]=$this->fixture('unknown');
        $this->actingAs($assessor)->post(route('ccr.regulatory-reviews.submit',$screen))->assertRedirect();
        $this->actingAs($admin)->post(route('ccr.regulatory-reviews.decide',$screen),['decision'=>'reviewed'])->assertStatus(409);
        $this->actingAs($owner)->get(route('ccr.regulatory-reviews.readiness',$ccr))->assertOk()->assertSee('Registration status/source requires verification.');
    }
    public function test_self_review_and_locked_ccr_are_blocked(): void
    {
        [$owner,$assessor,$admin,,$ccr,$screen]=$this->fixture();
        $this->actingAs($assessor)->post(route('ccr.regulatory-reviews.submit',$screen))->assertRedirect();
        $this->actingAs($assessor)->get(route('ccr.regulatory-reviews.show',$screen))->assertForbidden();
        DB::table('change_control_records')->where('id',$ccr)->update(['status'=>'pending_approval']);
        $this->actingAs($admin)->post(route('ccr.regulatory-reviews.decide',$screen),['decision'=>'reviewed'])->assertStatus(409);
    }
}
