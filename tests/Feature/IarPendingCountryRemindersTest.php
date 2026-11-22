<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Iar\ReminderPreview;
use App\Services\Iar\ReminderStager;
use App\Services\Iar\ReminderEmailPreview;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IarPendingCountryRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $withAssignment = true): array
    {
        $creator = User::factory()->create(['is_active' => true]);
        $assessor = User::factory()->create(['is_active' => true]);
        $outsider = User::factory()->create(['is_active' => true]);
        $submitted = CarbonImmutable::parse('2026-10-01T09:00:00Z');
        $iarId = DB::table('impact_assessments')->insertGetId([
            'iar_number' => 'IAR-6C3-UAT', 'ccr_number' => 'CCR-6C3', 'creator_id' => $creator->id,
            'current_version' => 'Old', 'new_version' => 'New', 'sap_udi_icc_impacted' => 0,
            'labelling_impacted' => 0, 'is_urgent' => 0, 'status' => 'submitted',
            'submitted_at' => $submitted->toDateTimeString(),
            'due_at' => '2026-11-30 09:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $region = DB::table('regions')->insertGetId([
            'code' => '6C3', 'name' => 'Reminder 6C3', 'assignment_strategy' => 'country_assessor',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = [];
        foreach ([['QK', 'QKK', 'United Kingdom'], ['QF', 'QFF', 'France'], ['QG', 'QGG', 'Germany'], ['QI', 'QII', 'Italy']] as [$iso2, $iso3, $name]) {
            $ids[$name] = DB::table('countries')->insertGetId([
                'region_id' => $region, 'iso2' => $iso2, 'iso3' => $iso3,
                'name' => $name, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        if ($withAssignment) {
            DB::table('iar_assignments')->insert([
                'impact_assessment_id' => $iarId, 'country_id' => $ids['United Kingdom'],
                'assigned_to_user_id' => $assessor->id, 'assignment_source' => 'country_assessor',
                'status' => 'assigned', 'due_at' => '2026-11-30 09:00:00', 'assigned_at' => $submitted,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['France', 'Germany', 'Italy'] as $name) {
            DB::table('assignment_exceptions')->insert([
                'impact_assessment_id' => $iarId, 'country_id' => $ids[$name],
                'exception_type' => 'assessor_unavailable',
                'resolution_status' => $name === 'Germany' ? 'resolved' : 'open',
                'details' => 'Primary mapping missing', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return [$creator, $assessor, $outsider, $iarId, $ids, $submitted];
    }

    public function test_creator_sees_both_types_assessor_only_own_and_email_is_never_sent(): void
    {
        Mail::fake();
        [$creator, $assessor, $outsider, $iar, $ids, $submitted] = $this->scenario();
        $svc = app(ReminderPreview::class);
        $this->assertSame([], $svc->due($submitted->addDays(13)));
        $due = $svc->due($submitted->addDays(14));
        $this->assertCount(1, $due);
        $this->assertSame(3, $due[0]['pending_country_count']);
        $this->assertSame(1, $due[0]['assigned_pending_count']);
        $this->assertSame(2, $due[0]['unassigned_country_count']);
        $this->assertSame(['France', 'Italy', 'United Kingdom'], $due[0]['recipients'][0]['countries']);
        $this->assertSame(['France', 'Italy'], $due[0]['recipients'][0]['unassigned_countries']);
        $this->assertSame(['United Kingdom'], $due[0]['recipients'][1]['countries']);
        $this->assertArrayNotHasKey('unassigned_countries', $due[0]['recipients'][1]);
        $this->assertSame(2, $svc->due($submitted->addDays(21))[0]['cycle']);
        $messages = app(ReminderEmailPreview::class)->messages($due);
        $this->assertCount(2, $messages);
        $this->assertStringContainsString('Pending assessor assignment', $messages[0]['body']);
        $this->assertStringContainsString('France', $messages[0]['body']);
        $this->assertStringNotContainsString('France', $messages[1]['body']);
        $this->assertStringNotContainsString('Italy', $messages[1]['body']);
        $first = app(ReminderStager::class)->stage($submitted->addDays(14));
        $this->assertSame(2, $first['created']);
        $this->assertSame(2, app(ReminderStager::class)->stage($submitted->addDays(14))['existing']);
        $this->actingAs($creator)->get(route('iar.reminders'))->assertOk()
            ->assertSee('France')->assertSee('Italy')->assertSee('United Kingdom');
        $this->actingAs($assessor)->get(route('iar.reminders'))->assertOk()
            ->assertSee('United Kingdom')->assertDontSee('Pending assessor assignment: France');
        $this->actingAs($outsider)->get(route('iar.reminders'))->assertOk()
            ->assertDontSee('IAR-6C3-UAT');
        Mail::assertNothingSent();
    }

    public function test_exception_only_still_reminds_creator_and_resolved_country_does_not_repeat(): void
    {
        [$creator, $assessor, $outsider, $iarId, $ids, $submitted] = $this->scenario(false);
        $due = app(ReminderPreview::class)->due($submitted->addDays(14));
        $this->assertCount(1, $due);
        $this->assertSame(2, $due[0]['pending_country_count']);
        $this->assertSame(0, $due[0]['assigned_pending_count']);
        $this->assertSame(2, $due[0]['unassigned_country_count']);
        $this->assertCount(1, $due[0]['recipients']);
        $this->assertSame('creator', $due[0]['recipients'][0]['role']);
        $this->assertSame(['France', 'Italy'], $due[0]['recipients'][0]['countries']);
        // Simulate a resolved mapping with an assignment for France.
        DB::table('iar_assignments')->insert([
            'impact_assessment_id' => $iarId, 'country_id' => $ids['France'],
            'assigned_to_user_id' => $assessor->id, 'assignment_source' => 'country_assessor',
            'status' => 'assigned', 'due_at' => '2026-11-30 09:00:00', 'assigned_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $after = app(ReminderPreview::class)->due($submitted->addDays(14))[0];
        $this->assertSame(1, $after['unassigned_country_count']);
        $this->assertSame(['Italy'], $after['recipients'][0]['unassigned_countries']);
        $this->assertSame(2, $after['pending_country_count']);
        // Once the newly assigned country completes, it must disappear entirely.
        DB::table('iar_assignments')->where('impact_assessment_id', $iarId)
            ->where('country_id', $ids['France'])->update(['completed_at' => now()]);
        $finished = app(ReminderPreview::class)->due($submitted->addDays(14))[0];
        $this->assertSame(1, $finished['pending_country_count']);
        $this->assertSame(['Italy'], $finished['recipients'][0]['countries']);
        $this->assertCount(1, $finished['recipients']);
    }
}
