<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Ccr\ImpactAreaCatalog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CcrRegulatoryMarketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function fixture(bool $withDecisions = true): array
    {
        $owner = User::factory()->create(['is_active' => true]); $owner->assignRole('global-ra');
        $other = User::factory()->create(['is_active' => true]); $other->assignRole('local-ra');
        $now = now('UTC');
        $region = DB::table('regions')->insertGetId(['code' => 'TESTREG', 'name' => 'Test Region', 'assignment_strategy' => 'country_assessor', 'created_at' => $now, 'updated_at' => $now]);
        $countries = [];
        foreach (['US' => 'United States', 'GB' => 'United Kingdom', 'FR' => 'France'] as $iso => $name) {
            $countries[$iso] = DB::table('countries')->insertGetId(['region_id' => $region, 'iso2' => $iso,
                'iso3' => ['US' => 'USA', 'GB' => 'GBR', 'FR' => 'FRA'][$iso], 'name' => $name, 'created_at' => $now, 'updated_at' => $now]);
        }
        $p = DB::table('products')->insertGetId(['sap_code' => 'SYNTH-CCR4A', 'product_name' => 'Test device', 'created_at' => $now, 'updated_at' => $now]);
        $ccr = DB::table('change_control_records')->insertGetId(['ccr_number' => 'CCR-SCREEN-' . uniqid(), 'created_by' => $owner->id,
            'title' => 'Synthetic material supplier change', 'change_type' => 'materials', 'current_state' => 'A', 'proposed_state' => 'B',
            'reason_for_change' => 'Synthetic', 'change_duration' => 'permanent', 'status' => 'draft', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('ccr_products')->insert(['change_control_record_id' => $ccr, 'product_id' => $p, 'created_at' => $now, 'updated_at' => $now]);
        $registrationIds = [];
        foreach (['US' => 'active', 'GB' => 'unknown', 'FR' => 'inactive'] as $iso => $status) {
            $registrationIds[$iso] = DB::table('product_registrations')->insertGetId(['product_id' => $p, 'country_id' => $countries[$iso],
                'registration_status' => $status, 'created_at' => $now, 'updated_at' => $now]);
        }
        if ($withDecisions) foreach (ImpactAreaCatalog::AREAS as $code => $label) {
            DB::table('ccr_impact_decisions')->insert(['change_control_record_id' => $ccr, 'area_code' => $code,
                'decision' => 'not_selected', 'suggested_by_rule' => false, 'decided_by' => $owner->id,
                'rationale' => $code === 'regulatory' ? 'Synthetic exclusion despite mandatory market review.' : null,
                'created_at' => $now, 'updated_at' => $now]);
        }
        return [$owner, $other, $ccr, $p, $countries, $registrationIds];
    }

    public function test_regulatory_market_screening_is_mandatory_even_if_generic_regulatory_area_not_selected(): void
    {
        Mail::fake();
        [$owner, , $ccr, , , $registrations] = $this->fixture();
        $this->actingAs($owner)->get(route('ccr.regulatory-markets.index', $ccr))->assertOk()->assertSee('France');
        $this->assertDatabaseCount('ccr_regulatory_market_screenings', 0); // GET is read-only
        $this->actingAs($owner)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertRedirect();
        $this->assertDatabaseCount('ccr_regulatory_market_screenings', 3);
        $this->assertDatabaseCount('ccr_regulatory_market_sources', 3);
        $this->assertDatabaseHas('ccr_regulatory_market_sources', ['product_registration_id' => $registrations['FR'], 'source_registration_status' => 'inactive']);
        $this->assertDatabaseHas('ccr_regulatory_market_sources', ['product_registration_id' => $registrations['GB'], 'source_registration_status' => 'unknown']);
        $this->assertDatabaseHas('ccr_regulatory_market_screenings', ['status' => 'pending_regulatory_screening', 'jurisdiction_hint' => 'uk_jurisdiction_review']);
        $this->assertDatabaseHas('change_control_records', ['id' => $ccr, 'status' => 'draft']);
        Mail::assertNothingOutgoing();
    }

    public function test_repeat_and_additional_registration_do_not_duplicate_or_reset_audit(): void
    {
        [$owner, , $ccr, $p, $countries] = $this->fixture();
        $this->actingAs($owner)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertRedirect();
        $this->actingAs($owner)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertRedirect();
        $this->assertSame(1, DB::table('audit_logs')->where('entity_id', $ccr)->where('action', 'ccr.regulatory.market_sources.recorded')->count());
        $now = now('UTC');
        DB::table('product_registrations')->insert(['product_id' => $p, 'country_id' => $countries['US'], 'registration_status' => 'unknown', 'created_at' => $now, 'updated_at' => $now]);
        $this->actingAs($owner)->get(route('ccr.regulatory-markets.index', $ccr))->assertSee('1 new/unsnapshotted');
        $this->actingAs($owner)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertRedirect();
        $this->assertDatabaseCount('ccr_regulatory_market_screenings', 3);
        $this->assertDatabaseCount('ccr_regulatory_market_sources', 4);
        $this->assertSame(2, DB::table('audit_logs')->where('entity_id', $ccr)->where('action', 'ccr.regulatory.market_sources.recorded')->count());
    }

    public function test_unauthorized_user_and_locked_ccr_cannot_initialize(): void
    {
        [$owner, $other, $ccr] = $this->fixture();
        $this->actingAs($other)->get(route('ccr.regulatory-markets.index', $ccr))->assertForbidden();
        $this->actingAs($other)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertForbidden();
        DB::table('change_control_records')->where('id', $ccr)->update(['status' => 'pending_approval']);
        $this->actingAs($owner)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertStatus(409);
        $this->assertDatabaseCount('ccr_regulatory_market_screenings', 0);
    }

    public function test_incomplete_impact_decisions_block_initialization(): void
    {
        [$owner, , $ccr] = $this->fixture(false);
        $this->actingAs($owner)->post(route('ccr.regulatory-markets.initialize', $ccr))->assertStatus(409);
        $this->assertDatabaseCount('ccr_regulatory_market_screenings', 0);
    }
}
