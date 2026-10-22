<?php

namespace Tests\Feature;

use App\Models\AssessmentResponse;
use App\Models\IarAssignment;
use App\Models\ImpactAssessment;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignedAssessmentDraftTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create(['is_active' => true]);
        $owner->assignRole('local-ra');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('local-ra');
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $region = DB::table('regions')->insertGetId(['code' => 'TEST', 'name' => 'Test Region', 'assignment_strategy' => 'country_assessor', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $country = DB::table('countries')->insertGetId(['region_id' => $region, 'iso2' => 'TT', 'iso3' => 'TST', 'name' => 'Testland', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $iar = ImpactAssessment::create([
            'iar_number' => 'IAR-TEST-0001',
            'ccr_number' => 'CCR-TEST',
            'creator_id' => $creator->id,
            'current_version' => 'Old',
            'new_version' => 'New',
            'sap_udi_icc_impacted' => false,
            'labelling_impacted' => false,
            'is_urgent' => false,
            'due_at' => now()->addDays(3),
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
        $assignment = IarAssignment::create([
            'impact_assessment_id' => $iar->id,
            'country_id' => $country,
            'assigned_to_user_id' => $owner->id,
            'assignment_source' => 'country_assessor',
            'status' => 'assigned',
            'due_at' => now()->addDays(3),
            'assigned_at' => now(),
        ]);
        return [$owner, $other, $assignment];
    }

    public function test_assignee_sees_only_own_assignment_and_can_save_audited_draft(): void
    {
        [$owner, $other, $assignment] = $this->fixture();
        $this->actingAs($owner)->get(route('assessment.index'))->assertOk()->assertSee('IAR-TEST-0001');
        $this->actingAs($other)->get(route('assessment.index'))->assertOk()->assertDontSee('IAR-TEST-0001');
        $this->actingAs($owner)->post(route('assessment.save', $assignment), [
            'impact_type' => 'Regulatory',
            'timeline' => '30 days',
            'comments' => 'Review required',
            'stop_ship_required' => '0',
            'more_information_required' => '1',
            'changed_product_imported' => '0',
        ])->assertRedirect(route('assessment.edit', $assignment));
        $this->assertDatabaseHas('assessment_responses', ['iar_assignment_id' => $assignment->id, 'status' => 'draft', 'impact_type' => 'Regulatory']);
        $this->assertDatabaseHas('audit_logs', ['entity_type' => AssessmentResponse::class, 'action' => 'assessment.draft.created']);
        $this->actingAs($owner)->get(route('assessment.edit', $assignment))->assertOk()->assertSee('30 days');
    }

    public function test_another_user_cannot_view_or_modify_assignment(): void
    {
        [$owner, $other, $assignment] = $this->fixture();
        $this->actingAs($other)->get(route('assessment.edit', $assignment))->assertForbidden();
        $this->actingAs($other)->post(route('assessment.save', $assignment), ['comments' => 'Tamper'])->assertForbidden();
        $this->assertDatabaseCount('assessment_responses', 0);
    }

    public function test_unsubmitted_iar_cannot_receive_assessment_draft(): void
    {
        [$owner, $other, $assignment] = $this->fixture();
        $assignment->impactAssessment->update(['status' => 'draft', 'submitted_at' => null]);
        $this->actingAs($owner)->post(route('assessment.save', $assignment), ['comments' => 'Premature'])->assertSessionHasErrors('assessment');
        $this->assertDatabaseCount('assessment_responses', 0);
    }

    public function test_submitted_response_cannot_be_overwritten(): void
    {
        [$owner, $other, $assignment] = $this->fixture();
        AssessmentResponse::create(['iar_assignment_id' => $assignment->id, 'comments' => 'Final', 'status' => 'submitted', 'submitted_at' => now()]);
        $this->actingAs($owner)->post(route('assessment.save', $assignment), ['comments' => 'Changed'])->assertSessionHasErrors('assessment');
        $this->assertDatabaseHas('assessment_responses', ['iar_assignment_id' => $assignment->id, 'comments' => 'Final', 'status' => 'submitted']);
    }


    public function test_inactive_assignee_cannot_access_workspace(): void
    {
        [$owner, $other, $assignment] = $this->fixture();

        $owner->update(['is_active' => false]);

        // Inactive assignee cannot open the workspace.
        $this->actingAs($owner)
            ->get(route('assessment.index'))
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // Inactive assignee cannot save a draft either.
        $this->actingAs($owner)
            ->post(route('assessment.save', $assignment), [
                'impact_type' => 'No impact',
                'comments' => 'Should not be saved',
            ])
            ->assertRedirect(route('login'));

        $this->assertGuest();

        // No assessment response should be created.
        $this->assertDatabaseMissing('assessment_responses', [
            'iar_assignment_id' => $assignment->id,
        ]);
    }

}
