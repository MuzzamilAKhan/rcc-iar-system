<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Iar\ReminderStager;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IarReminderStagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_opt_in_stage_is_idempotent_scoped_and_never_sends_email(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $creator = User::factory()->create(['is_active' => true]);
        $creator->assignRole('global-ra');
        $assessor = User::factory()->create(['is_active' => true]);
        $assessor->assignRole('local-ra');
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole('local-ra');
        $submitted = CarbonImmutable::parse('2026-01-01T10:00:00Z');
        $iar = DB::table('impact_assessments')->insertGetId([
            'iar_number'=>'IAR-UAT-REMINDER-001', 'ccr_number'=>'CCR-UAT', 'creator_id'=>$creator->id,
            'current_version'=>'old','new_version'=>'new','sap_udi_icc_impacted'=>0,'labelling_impacted'=>0,
            'is_urgent'=>0,'status'=>'submitted','due_at'=>$submitted->addMonth()->toDateTimeString(),
            'submitted_at'=>$submitted->toDateTimeString(), 'created_at'=>$submitted,'updated_at'=>$submitted,
        ]);
        $region = DB::table('regions')->insertGetId(['code'=>'UAT6B','name'=>'UAT', 'is_active'=>1,
            'assignment_strategy'=>'country_assessor','created_at'=>now(),'updated_at'=>now()]);
        $country = DB::table('countries')->insertGetId(['region_id'=>$region,'iso2'=>'ZX','iso3'=>'ZXX',
            'name'=>'UAT Country','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
        DB::table('iar_assignments')->insert(['impact_assessment_id'=>$iar,'country_id'=>$country,
            'assigned_to_user_id'=>$assessor->id,'assignment_source'=>'country_assessor','status'=>'assigned',
            'due_at'=>$submitted->addMonth()->toDateTimeString(),'assigned_at'=>$submitted,
            'created_at'=>$submitted,'updated_at'=>$submitted]);
        $asOf=$submitted->addDays(14);
        $this->assertSame(0, DB::table('notification_logs')->count());
        $first = app(ReminderStager::class)->stage($asOf);
        $this->assertSame(2, $first['created']);
        $second = app(ReminderStager::class)->stage($asOf);
        $this->assertSame(0, $second['created']);
        $this->assertSame(2, $second['existing']);
        $this->assertSame(2, DB::table('notification_logs')->count());
        $this->assertEqualsCanonicalizing(['iar.reminder.creator','iar.reminder.assessor'],
            DB::table('notification_logs')->pluck('event_type')->all());
        $this->actingAs($other)->get(route('iar.reminders'))->assertOk()->assertDontSee('IAR-UAT-REMINDER-001');
        $this->actingAs($creator)->get(route('iar.reminders'))->assertOk()->assertSee('IAR-UAT-REMINDER-001');
    }
}
