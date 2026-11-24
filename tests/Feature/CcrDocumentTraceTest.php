<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrDocumentTraceTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    private function fixture(): array
    {
        $owner=User::factory()->create(['is_active'=>true]); $owner->assignRole('global-ra');
        $outsider=User::factory()->create(['is_active'=>true]); $outsider->assignRole('local-ra');
        $now=now('UTC');
        $ccr=DB::table('change_control_records')->insertGetId([
            'ccr_number'=>'CCR-DOC-'.uniqid(),'created_by'=>$owner->id,'title'=>'Synthetic supplier change',
            'change_type'=>'materials','current_state'=>'A','proposed_state'=>'B','reason_for_change'=>'Test only',
            'change_duration'=>'permanent','status'=>'draft','created_at'=>$now,'updated_at'=>$now]);
        return [$owner,$outsider,$ccr];
    }
    private function doc(): array
    {
        return ['document_identifier'=>'TEST-SPEC-001','document_revision'=>'Rev 2',
            'document_title'=>'Synthetic component material specification',
            'impact_rationale'=>'Potential material substitution affects qualification evidence.'];
    }
    public function test_owner_records_potential_document_and_explicitly_confirms_without_approval(): void
    {
        Mail::fake();[$owner,,$ccr]=$this->fixture();
        $this->actingAs($owner)->get(route('ccr.documents.index',$ccr))->assertOk()->assertSee('potentially affected');
        $this->actingAs($owner)->post(route('ccr.documents.add',$ccr),$this->doc())->assertRedirect();
        $this->assertDatabaseHas('ccr_document_impacts',['change_control_record_id'=>$ccr,'status'=>'potential']);
        $docId=DB::table('ccr_document_impacts')->value('id');
        $this->actingAs($owner)->post(route('ccr.documents.confirm',[$ccr,$docId]),[
            'confirmation_rationale'=>'Confirmed this specification must be assessed for the supplier change.'])->assertRedirect();
        $this->assertDatabaseHas('ccr_document_impacts',['id'=>$docId,'status'=>'confirmed','confirmed_by_user_id'=>$owner->id]);
        $this->assertDatabaseHas('change_control_records',['id'=>$ccr,'status'=>'draft']);
        $this->assertSame(1,DB::table('audit_logs')->where('entity_id',$ccr)->where('action','ccr.document.impact.confirmed')->count());
        Mail::assertNothingOutgoing();
    }
    public function test_duplicate_document_and_repeat_confirmation_are_rejected(): void
    {
        [$owner,,$ccr]=$this->fixture();
        $this->actingAs($owner)->post(route('ccr.documents.add',$ccr),$this->doc())->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.documents.add',$ccr),$this->doc())->assertStatus(409);
        $docId=DB::table('ccr_document_impacts')->value('id');
        $this->actingAs($owner)->post(route('ccr.documents.confirm',[$ccr,$docId]),[
            'confirmation_rationale'=>'Verified that this document is impacted by the change.'])->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.documents.confirm',[$ccr,$docId]),[
            'confirmation_rationale'=>'Verified that this document is impacted by the change.'])->assertStatus(409);
        $this->assertDatabaseCount('ccr_document_impacts',1);
    }
    public function test_evidence_metadata_is_unverified_and_cross_ccr_links_are_rejected(): void
    {
        [$owner,,$ccr]=$this->fixture();
        [$otherOwner,,$otherCcr]=$this->fixture();
        $this->actingAs($otherOwner)->post(route('ccr.documents.add',$otherCcr),$this->doc())->assertRedirect();
        $foreignDoc=DB::table('ccr_document_impacts')->where('change_control_record_id',$otherCcr)->value('id');
        $payload=['reference_identifier'=>'TEST-QUAL-001','reference_revision'=>'Rev 1',
            'evidence_description'=>'Synthetic qualification evidence reference only.',
            'document_impact_id'=>$foreignDoc];
        $this->actingAs($owner)->post(route('ccr.documents.evidence',$ccr),$payload)->assertStatus(422);
        unset($payload['document_impact_id']);
        $this->actingAs($owner)->post(route('ccr.documents.evidence',$ccr),$payload)->assertRedirect();
        $this->assertDatabaseHas('ccr_evidence_references',['change_control_record_id'=>$ccr,'evidence_status'=>'referenced']);
        $this->assertDatabaseHas('change_control_records',['id'=>$ccr,'status'=>'draft']);
    }
    public function test_unrelated_user_locked_ccr_and_invalid_impact_area_block_changes(): void
    {
        [$owner,$outsider,$ccr]=$this->fixture();
        $this->actingAs($outsider)->get(route('ccr.documents.index',$ccr))->assertForbidden();
        $this->actingAs($outsider)->post(route('ccr.documents.add',$ccr),$this->doc())->assertForbidden();
        $bad=$this->doc(); $bad['impact_area_code']='materials';
        $this->actingAs($owner)->post(route('ccr.documents.add',$ccr),$bad)->assertStatus(422);
        DB::table('change_control_records')->where('id',$ccr)->update(['status'=>'pending_approval']);
        $this->actingAs($owner)->post(route('ccr.documents.add',$ccr),$this->doc())->assertStatus(409);
        $this->assertDatabaseCount('ccr_document_impacts',0);
    }
}
