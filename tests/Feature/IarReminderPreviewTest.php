<?php

namespace Tests\Feature;

use App\Models\ImpactAssessment;
use App\Models\User;
use App\Services\Iar\ReminderPreview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IarReminderPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminder_starts_after_fourteen_calendar_days_then_every_seven_days(): void
    {
        $creator = User::factory()->create(['is_active' => true]);
        $iar = ImpactAssessment::create([
            'iar_number' => 'IAR-REMIND-001', 'ccr_number' => 'CCR-REMIND-001',
            'creator_id' => $creator->id, 'current_version' => 'Old', 'new_version' => 'New',
            'sap_udi_icc_impacted' => false, 'labelling_impacted' => false, 'is_urgent' => false,
            'due_at' => '2026-12-31 00:00:00', 'status' => 'submitted',
            'submitted_at' => '2026-10-01 09:00:00',
        ]);
        $region = DB::table('regions')->insertGetId(['code' => 'REM-REG', 'name' => 'Reminder Region',
            'assignment_strategy' => 'creator', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $country = DB::table('countries')->insertGetId(['region_id' => $region, 'iso2' => 'XZ',
            'iso3' => 'XZZ', 'name' => 'Reminder Country', 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $assignee = User::factory()->create(['is_active' => true]);
        DB::table('iar_assignments')->insert(['impact_assessment_id' => $iar->id, 'country_id' => $country,
            'assigned_to_user_id' => $assignee->id, 'assignment_source' => 'creator',
            'status' => 'assigned', 'due_at' => '2026-12-31 00:00:00', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now()]);
        $svc = app(ReminderPreview::class);
        $this->assertSame([], $svc->due(CarbonImmutable::parse('2026-10-15 08:59:59', 'UTC')));
        $first = $svc->due(CarbonImmutable::parse('2026-10-15 09:00:00', 'UTC'));
        $this->assertCount(1, $first);
        $this->assertSame(1, $first[0]['cycle']);
        $this->assertSame(2, count($first[0]['recipients']));
        $this->assertSame(['Reminder Country'], $first[0]['recipients'][0]['countries']);
        $this->assertSame(2, $svc->due(CarbonImmutable::parse('2026-10-22 09:00:00', 'UTC'))[0]['cycle']);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
