<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ccr\ImpactAreaCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CcrImpactSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function ccr(User $owner, string $type = 'materials', string $status = 'draft'): int
    {
        return DB::table('change_control_records')->insertGetId([
            'ccr_number' => 'CCR-TEST-' . uniqid(),
            'created_by' => $owner->id,
            'title' => 'Synthetic material substitution',
            'change_type' => $type,
            'current_state' => 'Current material A',
            'proposed_state' => 'Proposed material B',
            'reason_for_change' => 'Supplier capacity',
            'change_duration' => 'permanent',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function decisions(): array
    {
        return array_fill_keys(array_keys(ImpactAreaCatalog::AREAS), 'selected');
    }

    public function test_creator_saves_16_decisions_once_and_repeated_submit_does_not_repeat_audit(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        $id = $this->ccr($user);
        $this->actingAs($user)->get(route('ccr.impacts.edit', $id))->assertOk()->assertSee('Cross-functional impact selection');
        $data = ['decisions' => $this->decisions()];
        $this->actingAs($user)->post(route('ccr.impacts.update', $id), $data)->assertRedirect();
        $this->assertSame(16, DB::table('ccr_impact_decisions')->where('change_control_record_id', $id)->count());
        $first = DB::table('audit_logs')->where('entity_type', 'change_control_record')->where('entity_id', $id)->count();
        $this->actingAs($user)->post(route('ccr.impacts.update', $id), $data)->assertRedirect();
        $this->assertSame(16, DB::table('ccr_impact_decisions')->where('change_control_record_id', $id)->count());
        $this->assertSame($first, DB::table('audit_logs')->where('entity_type', 'change_control_record')->where('entity_id', $id)->count());
        $this->assertDatabaseHas('change_control_records', ['id' => $id, 'status' => 'draft']);
    }

    public function test_suggested_significant_area_requires_exclusion_rationale(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        $id = $this->ccr($user);
        $decisions = $this->decisions();
        $decisions['regulatory'] = 'not_selected';
        $this->actingAs($user)->post(route('ccr.impacts.update', $id), ['decisions' => $decisions])
            ->assertSessionHasErrors('rationales.regulatory');
        $this->assertDatabaseCount('ccr_impact_decisions', 0);
        $this->actingAs($user)->post(route('ccr.impacts.update', $id), [
            'decisions' => $decisions,
            'rationales' => ['regulatory' => 'Initial assessment suggests no direct regulatory modification, pending mandatory screening.'],
        ])->assertRedirect();
        $this->assertDatabaseHas('ccr_impact_decisions', ['change_control_record_id' => $id, 'area_code' => 'regulatory', 'decision' => 'not_selected']);
    }

    public function test_unrelated_actor_cannot_change_decisions_and_locked_ccr_is_not_editable(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('global-ra');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('global-ra');
        $id = $this->ccr($owner);
        $this->actingAs($other)->get(route('ccr.impacts.edit', $id))->assertForbidden();
        $this->actingAs($other)->post(route('ccr.impacts.update', $id), ['decisions' => $this->decisions()])->assertForbidden();
        DB::table('change_control_records')->where('id', $id)->update(['status' => 'pending_approval']);
        $this->actingAs($owner)->post(route('ccr.impacts.update', $id), ['decisions' => $this->decisions()])->assertStatus(409);
        $this->assertDatabaseCount('ccr_impact_decisions', 0);
    }

    public function test_unknown_area_is_rejected(): void
    {
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('global-ra');
        $id = $this->ccr($owner);
        $data = $this->decisions();
        $data['random_area'] = 'selected';
        $this->actingAs($owner)->post(route('ccr.impacts.update', $id), ['decisions' => $data])->assertSessionHasErrors();
        $this->assertDatabaseCount('ccr_impact_decisions', 0);
    }
}
