<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Iar\ReminderEmailOutboxStager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IarReminderEmailOutboxTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $creator = User::factory()->create(['is_active' => true]);
        $assessor = User::factory()->create(['is_active' => true]);
        $unrelated = User::factory()->create(['is_active' => true]);
        $submitted = CarbonImmutable::parse('2026-10-01T09:00:00Z');
        $iarId = DB::table('impact_assessments')->insertGetId([
            'iar_number' => 'IAR-EMAIL-OUTBOX-001', 'ccr_number' => 'CCR-OUTBOX-001',
            'creator_id' => $creator->id, 'current_version' => 'Before', 'new_version' => 'After',
            'sap_udi_icc_impacted' => false, 'labelling_impacted' => false, 'is_urgent' => false,
            'status' => 'submitted', 'submitted_at' => $submitted->toDateTimeString(),
            'due_at' => '2026-12-31 00:00:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $regionId = DB::table('regions')->insertGetId([
            'code' => 'OBX', 'name' => 'Outbox Region', 'assignment_strategy' => 'country_assessor',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $assignedCountry = DB::table('countries')->insertGetId([
            'region_id' => $regionId, 'iso2' => 'QX', 'iso3' => 'QXX', 'name' => 'Assigned Testland',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $unassignedCountry = DB::table('countries')->insertGetId([
            'region_id' => $regionId, 'iso2' => 'QY', 'iso3' => 'QYY', 'name' => 'Unassigned Testland',
            'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('iar_assignments')->insert([
            'impact_assessment_id' => $iarId, 'country_id' => $assignedCountry,
            'assigned_to_user_id' => $assessor->id, 'assignment_source' => 'country_assessor',
            'status' => 'assigned', 'due_at' => '2026-12-31 00:00:00',
            'assigned_at' => $submitted, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('assignment_exceptions')->insert([
            'impact_assessment_id' => $iarId, 'country_id' => $unassignedCountry,
            'exception_type' => 'assessor_unavailable', 'resolution_status' => 'open',
            'details' => 'Missing test mapping', 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$creator, $assessor, $unrelated, $iarId, $submitted];
    }

    public function test_outbox_is_idempotent_and_does_not_send_email(): void
    {
        Mail::fake();
        [$creator, $assessor, $unrelated, $iarId, $submitted] = $this->fixture();
        $service = app(ReminderEmailOutboxStager::class);
        $first = $service->stage($submitted->addDays(14));
        $this->assertSame(['created' => 2, 'existing' => 0, 'skipped' => 0], $first);
        $again = $service->stage($submitted->addDays(14));
        $this->assertSame(['created' => 0, 'existing' => 2, 'skipped' => 0], $again);
        $this->assertDatabaseCount('iar_email_outbox', 2);
        $this->assertDatabaseCount('notification_logs', 0);
        $this->assertSame(0, DB::table('iar_email_outbox')->where('recipient_user_id', $unrelated->id)->count());
        $this->assertEqualsCanonicalizing([$creator->id, $assessor->id],
            DB::table('iar_email_outbox')->pluck('recipient_user_id')->all());
        $this->assertSame(2, DB::table('iar_email_outbox')->where('status', 'pending')->where('attempt_count', 0)->count());
        Mail::assertNothingOutgoing();
    }

    public function test_email_contents_respect_creator_and_assessor_country_privacy(): void
    {
        Mail::fake();
        [$creator, $assessor, , , $submitted] = $this->fixture();
        app(ReminderEmailOutboxStager::class)->stage($submitted->addDays(14));
        $creatorBody = DB::table('iar_email_outbox')->where('recipient_user_id', $creator->id)->value('body');
        $assessorBody = DB::table('iar_email_outbox')->where('recipient_user_id', $assessor->id)->value('body');
        $this->assertStringContainsString('Assigned Testland', $creatorBody);
        $this->assertStringContainsString('Unassigned Testland', $creatorBody);
        $this->assertStringContainsString('Assigned Testland', $assessorBody);
        $this->assertStringNotContainsString('Unassigned Testland', $assessorBody);
        Mail::assertNothingOutgoing();
    }

    public function test_no_due_records_produce_no_outbox_entries(): void
    {
        Mail::fake();
        $this->assertSame(['created' => 0, 'existing' => 0, 'skipped' => 0],
            app(ReminderEmailOutboxStager::class)->stage());
        $this->assertDatabaseCount('iar_email_outbox', 0);
        Mail::assertNothingOutgoing();
    }
}
