<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrActionRegisterTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function fixture(): array
    {
        $owner=User::factory()->create(['is_active'=>true]);$owner->assignRole('global-ra');
        $assignee=User::factory()->create(['is_active'=>true]);$assignee->assignRole('local-ra');
        $other=User::factory()->create(['is_active'=>true]);$other->assignRole('global-ra');
        $p=Product::create(['sap_code'=>'SYNTH-CCR5A-'.uniqid(),'product_name'=>'Synthetic action device']);
        $now=now('UTC');
        $ccr=DB::table('change_control_records')->insertGetId([
            'ccr_number'=>'CCR-ACT-'.uniqid(),'created_by'=>$owner->id,'title'=>'Synthetic supplier action',
            'change_type'=>'materials','current_state'=>'A','proposed_state'=>'B',
            'reason_for_change'=>'Synthetic', 'change_duration'=>'permanent','status'=>'draft',
            'created_at'=>$now,'updated_at'=>$now]);
        DB::table('ccr_products')->insert(['change_control_record_id'=>$ccr,'product_id'=>$p->id,'created_at'=>$now,'updated_at'=>$now]);
        return [$owner,$assignee,$other,$ccr];
    }
    private function payload(int $userId): array
    {
        return ['title'=>'Confirm material specification','description'=>'Collect signed qualification evidence before action approval.',
            'owner_user_id'=>$userId,'due_date'=>now()->addDays(7)->toDateString()];
    }
    public function test_owner_creates_proposed_action_with_audit_but_no_approval_or_email(): void
    {
        Mail::fake();[$owner,$assignee,,$ccr]=$this->fixture();
        $this->actingAs($owner)->get(route('ccr.actions.index',$ccr))->assertOk();
        $this->actingAs($owner)->post(route('ccr.actions.store',$ccr),$this->payload($assignee->id))->assertRedirect();
        $this->assertDatabaseHas('ccr_actions',['change_control_record_id'=>$ccr,'owner_user_id'=>$assignee->id,'status'=>'proposed']);
        $this->assertDatabaseHas('audit_logs',['entity_id'=>$ccr,'action'=>'ccr.action.proposed']);
        $this->assertDatabaseHas('change_control_records',['id'=>$ccr,'status'=>'draft']);
        Mail::assertNothingOutgoing();
    }
    public function test_only_owner_or_admin_can_manage_and_assignee_queue_is_scoped(): void
    {
        [$owner,$assignee,$other,$ccr]=$this->fixture();
        $this->actingAs($other)->get(route('ccr.actions.index',$ccr))->assertForbidden();
        $this->actingAs($other)->post(route('ccr.actions.store',$ccr),$this->payload($other->id))->assertForbidden();
        $this->actingAs($owner)->post(route('ccr.actions.store',$ccr),$this->payload($assignee->id))->assertRedirect();
        $this->actingAs($other)->get(route('ccr.actions.my'))->assertDontSee('Confirm material specification');
        $this->actingAs($assignee)->get(route('ccr.actions.my'))->assertSee('Confirm material specification');
        $this->actingAs($assignee)->get(route('ccr.actions.index',$ccr))->assertForbidden();
    }
    public function test_cross_ccr_dependency_and_ineligible_owner_are_rejected(): void
    {
        [$owner,$assignee,,$ccr]=$this->fixture();
        [$owner2,$assignee2,,$ccr2]=$this->fixture();
        $this->actingAs($owner2)->post(route('ccr.actions.store',$ccr2),$this->payload($assignee2->id))->assertRedirect();
        $foreignAction=DB::table('ccr_actions')->where('change_control_record_id',$ccr2)->value('id');
        $this->actingAs($owner)->post(route('ccr.actions.store',$ccr),$this->payload($assignee->id)+['depends_on_action_id'=>$foreignAction])->assertStatus(422);
        $inactive=User::factory()->create(['is_active'=>false]);$inactive->assignRole('local-ra');
        $this->actingAs($owner)->post(route('ccr.actions.store',$ccr),$this->payload($inactive->id))->assertStatus(422);
        $this->assertSame(0,DB::table('ccr_actions')->where('change_control_record_id',$ccr)->count());
    }
    public function test_selected_impact_area_required_and_locked_ccr_rejects_create(): void
    {
        [$owner,$assignee,,$ccr]=$this->fixture();
        $data=$this->payload($assignee->id);$data['impact_area_code']='materials';
        $this->actingAs($owner)->post(route('ccr.actions.store',$ccr),$data)->assertStatus(422);
        DB::table('change_control_records')->where('id',$ccr)->update(['status'=>'pending_approval']);
        $this->actingAs($owner)->post(route('ccr.actions.store',$ccr),$this->payload($assignee->id))->assertStatus(409);
        $this->assertDatabaseCount('ccr_actions',0);
    }
}
