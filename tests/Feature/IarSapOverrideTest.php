<?php

namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\CreateIarDraft;
use App\Services\Iar\IarSubmissionReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IarSapOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $extra = []): array
    {
        return array_merge([
            'ccr_number' => 'MOCK-CCR-OVERRIDE',
            'current_version' => 'Synthetic prior condition',
            'new_version' => 'Synthetic proposed condition',
            'sap_udi_icc_impacted' => false,
            'labelling_impacted' => false,
            'is_urgent' => false,
            'due_at' => now()->addMonth()->format('Y-m-d H:i:s'),
            'bulk_sap_input' => "FAKE-UNKNOWN-001\nFAKE-UNKNOWN-001",
        ], $extra);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_missing_sap_requires_explicit_override_and_meaningful_reason(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        foreach ([$this->payload(), $this->payload(['override_warnings' => true, 'override_reason' => 'too short'])] as $data) {
            try {
                app(CreateIarDraft::class)->create($user, $data);
                $this->fail('Expected validation exception');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $this->assertSame(0, ImpactAssessment::count());
    }

    public function test_unmatched_sap_is_retained_and_audited_but_submission_blocked(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        $iar = app(CreateIarDraft::class)->create($user, $this->payload([
            'override_warnings' => true,
            'override_reason' => 'Registry entry is pending correction by master data owner.',
        ]));
        $rows = DB::table('iar_products')->where('impact_assessment_id', $iar->id)->get();
        $this->assertCount(1, $rows);
        $this->assertNull($rows[0]->product_id);
        $this->assertSame('FAKE-UNKNOWN-001', $rows[0]->submitted_sap_code);
        $this->assertSame(1, DB::table('audit_logs')->where('entity_id', $iar->id)->where('action', 'iar.sap.override.created')->count());
        $report = app(IarSubmissionReadiness::class)->inspect($iar);
        $this->assertFalse($report['can_submit']);
        $this->assertSame(1, $report['unresolved_sap_count']);
    }
}
