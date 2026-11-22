<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CcrSmeAssignmentTest extends TestCase
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
        $id = DB::table('change_control_records')->insertGetId([
            'ccr_number' => 'CCR-SME-' . uniqid(), 'created_by' => $owner->id,
            'title' => 'Synthetic materials change', 'change_type' => 'materials',
            'current_state' => 'Before', 'proposed_state' => 'After',
            'reason_for_change' => 'Synthetic test', 'change_duration' => 'permanent',
            'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['materials' => 'selected', 'software' => 'not_selected'] as $code => $decision) {
            DB::table('ccr_impact_decisions')->insert([
                'change_control_record_id' => $id, 'area_code' => $code, 'decision' => $decision,
                'suggested_by_rule' => false, 'decided_by' => $owner->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$owner, $assessor, $id];
    }

    public function test_selected_area_can_be_assigned_once_with_audit_and_no_notification(): void
    {
        [$owner, $assessor, $id] = $this->fixture();
        $this->actingAs($owner)->get(route('ccr.sme.index', $id))->assertOk()->assertSee('Materials & Biological Safety')->assertDontSee('Software</h2>', false);
        $payload = ['area_code' => 'materials', 'assigned_to_user_id' => $assessor->id];
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), $payload)->assertRedirect();
        $this->assertSame(1, DB::table('ccr_sme_assignments')->count());
        $this->assertDatabaseHas('audit_logs', ['entity_type' => 'change_control_record', 'entity_id' => $id, 'action' => 'ccr.sme.assigned']);
        $this->assertSame(1, DB::table('audit_logs')->where('entity_id', $id)->where('action', 'ccr.sme.assigned')->count());
        $this->assertSame(0, DB::table('notification_logs')->count());
    }

    public function test_not_selected_area_and_unrelated_actor_are_blocked(): void
    {
        [$owner, $assessor, $id] = $this->fixture();
        $payload = ['area_code' => 'software', 'assigned_to_user_id' => $assessor->id];
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), $payload)->assertStatus(422);
        $outsider = User::factory()->create(['is_active' => true]); $outsider->assignRole('global-ra');
        $this->actingAs($outsider)->get(route('ccr.sme.index', $id))->assertForbidden();
        $this->actingAs($outsider)->post(route('ccr.sme.store', $id), ['area_code' => 'materials', 'assigned_to_user_id' => $assessor->id])->assertForbidden();
        $this->assertDatabaseCount('ccr_sme_assignments', 0);
    }

    public function test_assignment_requires_active_ra_and_cannot_be_silently_changed(): void
    {
        [$owner, $assessor, $id] = $this->fixture();
        $inactive = User::factory()->create(['is_active' => false]); $inactive->assignRole('local-ra');
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), ['area_code' => 'materials', 'assigned_to_user_id' => $inactive->id])->assertStatus(422);
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), ['area_code' => 'materials', 'assigned_to_user_id' => $assessor->id])->assertRedirect();
        $other = User::factory()->create(['is_active' => true]); $other->assignRole('local-ra');
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), ['area_code' => 'materials', 'assigned_to_user_id' => $other->id])->assertStatus(409);
        $this->assertDatabaseCount('ccr_sme_assignments', 1);
    }

    public function test_locked_ccr_rejects_new_assignment(): void
    {
        [$owner, $assessor, $id] = $this->fixture();
        DB::table('change_control_records')->where('id', $id)->update(['status' => 'pending_approval']);
        $this->actingAs($owner)->post(route('ccr.sme.store', $id), ['area_code' => 'materials', 'assigned_to_user_id' => $assessor->id])->assertStatus(409);
    }
}
