<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CcrActionEvidenceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function fixture(): array
    {
        $owner = User::factory()->create(['is_active'=>true]); $owner->assignRole('local-ra');
        $admin = User::factory()->create(['is_active'=>true]); $admin->assignRole('super-admin');
        $other = User::factory()->create(['is_active'=>true]); $other->assignRole('global-ra');
        $now = now('UTC');
        $ccr = DB::table('change_control_records')->insertGetId([
            'ccr_number'=>'CCR-EV-'.uniqid(), 'created_by'=>$other->id,'title'=>'Synthetic change evidence',
            'change_type'=>'materials','current_state'=>'A','proposed_state'=>'B','reason_for_change'=>'Synthetic',
            'change_duration'=>'permanent','status'=>'draft','created_at'=>$now,'updated_at'=>$now,
        ]);
        $action=DB::table('ccr_actions')->insertGetId([
            'change_control_record_id'=>$ccr,'title'=>'Prepare qualification report','description'=>'Confirm test qualification source references.',
            'owner_user_id'=>$owner->id,'created_by_user_id'=>$other->id,'due_date'=>now()->addWeek()->toDateString(),
            'status'=>'proposed','created_at'=>$now,'updated_at'=>$now,
        ]);
        return [$owner,$admin,$other,$ccr,$action];
    }

    private function payload(): array
    {
        return ['owner_explanation'=>'Document reference is prepared for qualified independent review, not execution.',
            'evidence_reference'=>'TEST-ACTION-EV-001','evidence_revision'=>'Rev A'];
    }

    public function test_owner_saves_and_submits_without_completing_action(): void
    {
        [$owner,,$other,$ccr,$action]=$this->fixture();
        $this->actingAs($owner)->get(route('ccr.action-evidence.edit',$action))->assertOk();
        $this->actingAs($owner)->post(route('ccr.action-evidence.save',$action),$this->payload())->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.action-evidence.submit',$action))->assertRedirect();
        $this->assertDatabaseHas('ccr_action_evidence_reviews',['action_id'=>$action,'status'=>'submitted']);
        $this->assertDatabaseHas('ccr_actions',['id'=>$action,'status'=>'proposed']);
        $this->assertDatabaseHas('change_control_records',['id'=>$ccr,'status'=>'draft']);
        $this->actingAs($owner)->post(route('ccr.action-evidence.save',$action),$this->payload())->assertStatus(409);
        $this->actingAs($other)->get(route('ccr.action-evidence.edit',$action))->assertForbidden();
    }

    public function test_independent_admin_returns_and_owner_resubmits(): void
    {
        [$owner,$admin,,$ccr,$action]=$this->fixture();
        $this->actingAs($owner)->post(route('ccr.action-evidence.save',$action),$this->payload());
        $this->actingAs($owner)->post(route('ccr.action-evidence.submit',$action));
        $this->actingAs($admin)->get(route('ccr.action-evidence.queue'))->assertOk()->assertSee('Prepare qualification report');
        $this->actingAs($admin)->post(route('ccr.action-evidence.decide',$action),['decision'=>'returned','review_comment'=>'short'])->assertSessionHasErrors('review_comment');
        $this->actingAs($admin)->post(route('ccr.action-evidence.decide',$action),['decision'=>'returned','review_comment'=>'Provide the updated revision evidence.'])->assertRedirect();
        $this->assertDatabaseHas('ccr_action_evidence_reviews',['action_id'=>$action,'status'=>'returned']);
        $this->actingAs($owner)->post(route('ccr.action-evidence.save',$action),array_replace($this->payload(),['evidence_revision'=>'Rev B']))->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.action-evidence.submit',$action))->assertRedirect();
        $this->assertDatabaseHas('ccr_action_evidence_reviews',['action_id'=>$action,'status'=>'submitted','evidence_revision'=>'Rev B']);
    }

    public function test_admin_reviews_submission_but_action_stays_proposed(): void
    {
        [$owner,$admin,,$ccr,$action]=$this->fixture();
        $this->actingAs($owner)->post(route('ccr.action-evidence.save',$action),$this->payload());
        $this->actingAs($owner)->post(route('ccr.action-evidence.submit',$action));
        $this->actingAs($admin)->post(route('ccr.action-evidence.decide',$action),['decision'=>'reviewed','review_comment'=>'Reference metadata reviewed.'])->assertRedirect();
        $this->assertDatabaseHas('ccr_action_evidence_reviews',['action_id'=>$action,'status'=>'reviewed']);
        $this->assertDatabaseHas('ccr_actions',['id'=>$action,'status'=>'proposed']);
        $this->actingAs($admin)->post(route('ccr.action-evidence.decide',$action),['decision'=>'reviewed'])->assertStatus(409);
    }

    public function test_unauthorized_review_self_review_and_locked_ccr_are_blocked(): void
    {
        [$owner,$admin,$other,$ccr,$action]=$this->fixture();
        $this->actingAs($owner)->post(route('ccr.action-evidence.save',$action),$this->payload());
        $this->actingAs($owner)->post(route('ccr.action-evidence.submit',$action));
        $this->actingAs($owner)->get(route('ccr.action-evidence.review',$action))->assertForbidden();
        $this->actingAs($other)->post(route('ccr.action-evidence.decide',$action),['decision'=>'reviewed'])->assertForbidden();
        DB::table('change_control_records')->where('id',$ccr)->update(['status'=>'pending_approval']);
        $this->actingAs($admin)->post(route('ccr.action-evidence.decide',$action),['decision'=>'reviewed'])->assertStatus(409);
    }
}
