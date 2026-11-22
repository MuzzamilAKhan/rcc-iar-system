<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrSmeReviewTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function fixture(): array
    {
        $owner = User::factory()->create(['is_active'=>true]); $owner->assignRole('global-ra');
        $sme = User::factory()->create(['is_active'=>true]); $sme->assignRole('local-ra');
        $admin = User::factory()->create(['is_active'=>true]); $admin->assignRole('super-admin');
        $now=now('UTC');
        $ccr=DB::table('change_control_records')->insertGetId([
            'ccr_number'=>'CCR-REVIEW-'.uniqid(),'created_by'=>$owner->id,'title'=>'Synthetic material change',
            'change_type'=>'materials','current_state'=>'Existing material','proposed_state'=>'New material',
            'reason_for_change'=>'Test','change_duration'=>'permanent','status'=>'draft',
            'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::table('ccr_impact_decisions')->insert([
            'change_control_record_id'=>$ccr,'area_code'=>'materials','decision'=>'selected',
            'suggested_by_rule'=>true,'decided_by'=>$owner->id,'created_at'=>$now,'updated_at'=>$now,
        ]);
        $id=DB::table('ccr_sme_assignments')->insertGetId([
            'change_control_record_id'=>$ccr,'area_code'=>'materials','assigned_to_user_id'=>$sme->id,
            'assigned_by_user_id'=>$owner->id,'status'=>'draft','assigned_at'=>$now,'created_at'=>$now,'updated_at'=>$now,
        ]);
        DB::table('ccr_sme_responses')->insert([
            'ccr_sme_assignment_id'=>$id,'created_by_user_id'=>$sme->id,'answer'=>'uncertain',
            'impact_conclusion'=>'further_assessment','rationale'=>'A controlled material comparison is still required.',
            'evidence_references'=>'TEST-MAT Rev 1','created_at'=>$now,'updated_at'=>$now,
        ]);
        return [$owner,$sme,$admin,$ccr,$id];
    }

    public function test_only_assignee_can_submit_complete_draft_and_it_locks_without_email(): void
    {
        Mail::fake(); [$owner,$sme,$admin,$ccr,$id]=$this->fixture();
        $this->actingAs($owner)->post(route('ccr.sme-reviews.submit',$id))->assertForbidden();
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertRedirect();
        $this->assertDatabaseHas('ccr_sme_assignments',['id'=>$id,'status'=>'submitted']);
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertStatus(409);
        $this->actingAs($sme)->post(route('ccr.sme-drafts.save',$id),[
            'answer'=>'no','impact_conclusion'=>'no_impact','rationale'=>'Rewriting the already submitted response is forbidden.',
        ])->assertStatus(409);
        $this->assertDatabaseHas('ccr_sme_responses',['ccr_sme_assignment_id'=>$id,'answer'=>'uncertain']);
        $this->assertDatabaseHas('audit_logs',['entity_id'=>$ccr,'action'=>'ccr.sme.submitted']);
        Mail::assertNothingOutgoing();
    }

    public function test_independent_admin_reviews_once_and_assessor_cannot_self_review(): void
    {
        [$owner,$sme,$admin,$ccr,$id]=$this->fixture();
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertRedirect();
        $this->actingAs($owner)->get(route('ccr.sme-reviews.index'))->assertForbidden();
        $this->actingAs($sme)->post(route('ccr.sme-reviews.decide',$id),['decision'=>'reviewed'])->assertForbidden();
        $this->actingAs($admin)->get(route('ccr.sme-reviews.show',$id))->assertOk();
        $this->actingAs($admin)->post(route('ccr.sme-reviews.decide',$id),['decision'=>'reviewed'])->assertRedirect();
        $this->assertDatabaseHas('ccr_sme_assignments',['id'=>$id,'status'=>'reviewed','reviewed_by_user_id'=>$admin->id]);
        $this->actingAs($admin)->post(route('ccr.sme-reviews.decide',$id),['decision'=>'reviewed'])->assertStatus(409);
        $this->assertSame(1,DB::table('audit_logs')->where('entity_id',$ccr)->where('action','ccr.sme.review.decided')->count());
        $this->assertDatabaseHas('change_control_records',['id'=>$ccr,'status'=>'draft']);
    }

    public function test_return_requires_explanation_and_allows_sme_revision_and_resubmission(): void
    {
        [, $sme,$admin,,$id]=$this->fixture();
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertRedirect();
        $this->actingAs($admin)->post(route('ccr.sme-reviews.decide',$id),['decision'=>'returned'])->assertSessionHasErrors('review_comment');
        $this->actingAs($admin)->post(route('ccr.sme-reviews.decide',$id),[
            'decision'=>'returned','review_comment'=>'Please provide updated material verification evidence.',
        ])->assertRedirect();
        $this->assertDatabaseHas('ccr_sme_assignments',['id'=>$id,'status'=>'returned']);
        $this->actingAs($sme)->post(route('ccr.sme-drafts.save',$id),[
            'answer'=>'yes','impact_conclusion'=>'potential_impact',
            'rationale'=>'Additional synthetic testing indicates a possible material impact.',
            'evidence_references'=>'TEST-MAT Rev 2',
        ])->assertRedirect();
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertRedirect();
        $this->assertDatabaseHas('ccr_sme_assignments',['id'=>$id,'status'=>'submitted']);
    }

    public function test_missing_draft_and_locked_ccr_do_not_submit(): void
    {
        [,$sme,, $ccr,$id]=$this->fixture();
        DB::table('ccr_sme_responses')->where('ccr_sme_assignment_id',$id)->delete();
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertStatus(422);
        DB::table('change_control_records')->where('id',$ccr)->update(['status'=>'pending_approval']);
        $this->actingAs($sme)->post(route('ccr.sme-reviews.submit',$id))->assertStatus(409);
    }
}
