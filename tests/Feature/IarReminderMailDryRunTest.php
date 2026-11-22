<?php

namespace Tests\Feature;

use App\Mail\IarReminderMail;
use App\Models\User;
use App\Services\Iar\ReminderMailDryRun;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IarReminderMailDryRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailables_are_renderable_scoped_and_never_sent(): void
    {
        Mail::fake();
        $creator = User::factory()->create(['is_active' => true]);
        $assessor = User::factory()->create(['is_active' => true]);
        $submitted = CarbonImmutable::parse('2026-10-01 09:00:00', 'UTC');
        $iarId = DB::table('impact_assessments')->insertGetId([
            'iar_number'=>'IAR-MAIL-DRY-001','ccr_number'=>'CCR-MAIL-DRY-001',
            'creator_id'=>$creator->id,'current_version'=>'Before','new_version'=>'After',
            'sap_udi_icc_impacted'=>false,'labelling_impacted'=>false,'is_urgent'=>false,
            'status'=>'submitted','submitted_at'=>$submitted->toDateTimeString(),
            'due_at'=>'2026-12-31 00:00:00','created_at'=>now(),'updated_at'=>now(),
        ]);
        $region = DB::table('regions')->insertGetId([
            'code'=>'MAILR','name'=>'Mail Region','assignment_strategy'=>'country_assessor',
            'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $ukId = DB::table('countries')->insertGetId([
            'region_id'=>$region,'iso2'=>'XY','iso3'=>'XYY','name'=>'Test Assigned Country',
            'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $frId = DB::table('countries')->insertGetId([
            'region_id'=>$region,'iso2'=>'XW','iso3'=>'XWW','name'=>'Test Unassigned Country',
            'is_active'=>1,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('iar_assignments')->insert([
            'impact_assessment_id'=>$iarId,'country_id'=>$ukId,
            'assigned_to_user_id'=>$assessor->id,'assignment_source'=>'country_assessor',
            'status'=>'assigned','due_at'=>'2026-12-31 00:00:00','assigned_at'=>$submitted,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('assignment_exceptions')->insert([
            'impact_assessment_id'=>$iarId,'country_id'=>$frId,'exception_type'=>'assessor_unavailable',
            'resolution_status'=>'open','details'=>'No primary assessor',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $rows = app(ReminderMailDryRun::class)->prepare($submitted->addDays(14));
        $this->assertCount(2, $rows);
        $this->assertSame(['creator','assessor'], array_column($rows,'role'));
        $this->assertContainsOnlyInstancesOf(IarReminderMail::class, array_column($rows, 'mailable'));
        $creatorHtml = $rows[0]['mailable']->render();
        $assessorHtml = $rows[1]['mailable']->render();
        $this->assertStringContainsString('Test Assigned Country', $creatorHtml);
        $this->assertStringContainsString('Test Unassigned Country', $creatorHtml);
        $this->assertStringContainsString('Test Assigned Country', $assessorHtml);
        $this->assertStringNotContainsString('Test Unassigned Country', $assessorHtml);
        $this->assertDatabaseCount('notification_logs', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_no_due_reminders_means_no_mailables(): void
    {
        Mail::fake();
        $this->assertSame([], app(ReminderMailDryRun::class)->prepare());
        Mail::assertNothingOutgoing();
    }
}
