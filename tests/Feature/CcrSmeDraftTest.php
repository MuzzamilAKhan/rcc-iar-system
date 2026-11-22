<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrSmeDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['is_active' => true]); $owner->assignRole('global-ra');
        $assessor = User::factory()->create(['is_active' => true]); $assessor->assignRole('local-ra');
        $other = User::factory()->create(['is_active' => true]); $other->assignRole('local-ra');
        $now = now('UTC');
        $ccr = DB::table('change_control_records')->insertGetId([
            'ccr_number' => 'CCR-DRAFT-' . uniqid(), 'created_by' => $owner->id,
            'title' => 'Synthetic supplier material change', 'change_type' => 'materials',
            'current_state' => 'Old material', 'proposed_state' => 'New material',
            'reason_for_change' => 'Synthetic test', 'change_duration' => 'permanent',
            'status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('ccr_impact_decisions')->insert([
            'change_control_record_id' => $ccr, 'area_code' => 'materials', 'decision' => 'selected',
            'suggested_by_rule' => true, 'decided_by' => $owner->id,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $assignment = DB::table('ccr_sme_assignments')->insertGetId([
            'change_control_record_id' => $ccr, 'area_code' => 'materials',
            'assigned_to_user_id' => $assessor->id, 'assigned_by_user_id' => $owner->id,
            'status' => 'assigned', 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return [$owner, $assessor, $other, $ccr, $assignment];
    }

    private function payload(): array
    {
        return ['answer' => 'uncertain', 'impact_conclusion' => 'further_assessment',
            'rationale' => 'Supplier B material properties require a documented equivalence review.',
            'evidence_references' => 'TEST-MAT-SPEC Rev 1; TEST-RISK-LOG Rev 2'];
    }

    public function test_only_assignee_can_open_and_save_scoped_draft_without_email(): void
    {
        Mail::fake();
        [$owner, $assessor, $other, $ccr, $assignment] = $this->fixture();
        $this->actingAs($assessor)->get(route('ccr.sme-drafts.index'))->assertOk()->assertSee('Synthetic supplier');
        $this->actingAs($other)->get(route('ccr.sme-drafts.index'))->assertOk()->assertDontSee('Synthetic supplier');
        $this->actingAs($assessor)->get(route('ccr.sme-drafts.edit', $assignment))->assertOk()->assertSee('Materials & Biological Safety');
        $this->actingAs($assessor)->post(route('ccr.sme-drafts.save', $assignment), $this->payload())
            ->assertRedirect(route('ccr.sme-drafts.edit', $assignment));
        $this->assertDatabaseHas('ccr_sme_responses', ['ccr_sme_assignment_id' => $assignment,
            'answer' => 'uncertain', 'created_by_user_id' => $assessor->id]);
        $this->assertDatabaseHas('ccr_sme_assignments', ['id' => $assignment, 'status' => 'draft']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'change_control_record', 'entity_id' => $ccr,
            'action' => 'ccr.sme.draft.saved']);
        $this->assertDatabaseCount('notification_logs', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_unrelated_user_and_creator_cannot_edit_assignee_draft(): void
    {
        [$owner, $assessor, $other, , $assignment] = $this->fixture();
        $this->actingAs($owner)->get(route('ccr.sme-drafts.edit', $assignment))->assertForbidden();
        $this->actingAs($other)->post(route('ccr.sme-drafts.save', $assignment), $this->payload())->assertForbidden();
        $this->assertDatabaseCount('ccr_sme_responses', 0);
    }

    public function test_repeated_save_is_idempotent_and_edits_are_audited(): void
    {
        [, $assessor, , $ccr, $assignment] = $this->fixture();
        $this->actingAs($assessor)->post(route('ccr.sme-drafts.save', $assignment), $this->payload())->assertRedirect();
        $this->actingAs($assessor)->post(route('ccr.sme-drafts.save', $assignment), $this->payload())->assertRedirect();
        $this->assertSame(1, DB::table('ccr_sme_responses')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('entity_id', $ccr)->where('action', 'ccr.sme.draft.saved')->count());
        $changed = $this->payload(); $changed['rationale'] = 'An updated synthetic rationale requires additional bench verification.';
        $this->actingAs($assessor)->post(route('ccr.sme-drafts.save', $assignment), $changed)->assertRedirect();
        $this->assertSame(2, DB::table('audit_logs')->where('entity_id', $ccr)->where('action', 'ccr.sme.draft.saved')->count());
    }

    public function test_validation_and_locked_ccr_block_writes(): void
    {
        [, $assessor, , $ccr, $assignment] = $this->fixture();
        $bad = $this->payload(); $bad['answer'] = 'approved';
        $this->actingAs($assessor)->post(route('ccr.sme-drafts.save', $assignment), $bad)->assertSessionHasErrors('answer');
        $this->assertDatabaseCount('ccr_sme_responses', 0);
        DB::table('change_control_records')->where('id', $ccr)->update(['status' => 'pending_approval']);
        $this->actingAs($assessor)->post(route('ccr.sme-drafts.save', $assignment), $this->payload())->assertStatus(409);
        $this->assertDatabaseCount('ccr_sme_responses', 0);
    }
}
