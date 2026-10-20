<?php

namespace Tests\Feature;

use App\Models\{Country, CountryAssessor, IarAssignment, ImpactAssessment, Product, ProductRegistration, Region, User};
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_domain_tables_exist(): void
    {
        foreach (['business_units','regions','countries','country_assessors','products','product_registrations',
            'impact_assessments','iar_products','iar_assignments','assessment_responses','attachments',
            'import_batches','import_errors','notification_logs','audit_logs','assignment_exceptions'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_roles_are_seeded_idempotently(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertSame(5, Role::count());
        $this->assertTrue(Role::findByName('global-ra')->hasPermissionTo('iar.create'));
        $this->assertFalse(Role::findByName('local-ra')->hasPermissionTo('iar.create'));
    }

    public function test_only_one_assignment_per_iar_and_country(): void
    {
        $creator = User::factory()->create();
        $region = Region::create(['code'=>'OTHER','name'=>'Other']);
        $country = Country::create(['region_id'=>$region->id,'iso2'=>'PK','iso3'=>'PAK','name'=>'Pakistan']);
        $iar = ImpactAssessment::create(['iar_number'=>'IAR-TEST-1','ccr_number'=>'CCR-1','creator_id'=>$creator->id,
            'current_version'=>'v1','new_version'=>'v2','sap_udi_icc_impacted'=>false,'labelling_impacted'=>false,
            'due_at'=>now()->addDays(14),'status'=>'draft']);
        $data=['impact_assessment_id'=>$iar->id,'country_id'=>$country->id,'assigned_to_user_id'=>$creator->id,
            'assignment_source'=>'creator','status'=>'assigned','due_at'=>$iar->due_at];
        IarAssignment::create($data);
        $this->expectException(QueryException::class);
        IarAssignment::create($data);
    }

    public function test_country_assessor_has_one_primary_slot(): void
    {
        $region=Region::create(['code'=>'OTHER','name'=>'Other']);
        $country=Country::create(['region_id'=>$region->id,'iso2'=>'PK','iso3'=>'PAK','name'=>'Pakistan']);
        CountryAssessor::create(['country_id'=>$country->id,'user_id'=>User::factory()->create()->id,'assignment_priority'=>1]);
        $this->expectException(QueryException::class);
        CountryAssessor::create(['country_id'=>$country->id,'user_id'=>User::factory()->create()->id,'assignment_priority'=>1]);
    }
}
