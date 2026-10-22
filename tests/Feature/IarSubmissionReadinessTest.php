<?php

namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\IarSubmissionReadiness;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IarSubmissionReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function draft(User $actor): ImpactAssessment
    {
        return ImpactAssessment::create([
            'iar_number' => 'IAR-TEST-READINESS-' . $actor->id,
            'ccr_number' => 'CCR-READINESS-TEST',
            'creator_id' => $actor->id,
            'current_version' => 'Current',
            'new_version' => 'Proposed',
            'sap_udi_icc_impacted' => false,
            'labelling_impacted' => false,
            'is_urgent' => false,
            'due_at' => now()->addWeek(),
            'status' => 'draft',
        ]);
    }

    public function test_empty_draft_is_blocked_without_database_writes(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');
        $iar = $this->draft($admin);

        $report = app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertFalse($report['can_submit']);
        $this->assertSame(0, $report['product_count']);
        $this->assertSame(0, $report['country_count']);
        $this->assertDatabaseCount('iar_assignments', 0);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertSame('draft', $iar->fresh()->status);
    }

    public function test_creator_can_view_preview_and_unrelated_ra_cannot(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('global-ra');
        $iar = $this->draft($creator);

        $this->actingAs($creator)
            ->get(route('iar.readiness', $iar))
            ->assertOk()->assertSee('Submission Readiness');
        $this->actingAs($other)
            ->get(route('iar.readiness', $iar))
            ->assertForbidden();
    }

    public function test_inactive_user_cannot_view_preview(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');
        $iar = $this->draft($admin);
        $admin->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('iar.readiness', $iar))
            ->assertRedirect();
    }
}
