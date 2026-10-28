<?php
namespace Tests\Feature;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PrimaryAssessorMappingTest extends TestCase {
 use RefreshDatabase;
 protected function setUp(): void {parent::setUp();$this->seed(RolesAndPermissionsSeeder::class);}
 public function test_super_admin_can_create_mapping_once_but_not_replace_it(): void {
  $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('super-admin');
  $local=User::factory()->create(['is_active'=>true,'provisioning_status'=>'approved']);$local->assignRole('local-ra');
  $r=DB::table('regions')->insertGetId(['code'=>'PM','name'=>'Primary mapping','assignment_strategy'=>'country_assessor','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $c=DB::table('countries')->insertGetId(['region_id'=>$r,'iso2'=>'PX','iso3'=>'PXX','name'=>'Primary sample','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $url=route('admin.primary-mapping.store');
  $this->actingAs($admin)->post($url,['country_id'=>$c,'user_id'=>$local->id])->assertRedirect();
  $this->assertDatabaseHas('country_assessors',['country_id'=>$c,'user_id'=>$local->id,'assignment_priority'=>1]);
  $this->actingAs($admin)->post($url,['country_id'=>$c,'user_id'=>$local->id])->assertSessionHasErrors('country_id');
  $this->assertSame(1,DB::table('country_assessors')->where('country_id',$c)->count());
  $this->assertDatabaseHas('audit_logs',['action'=>'country.primary_assessor.assigned']);
 }
 public function test_global_ra_cannot_change_mapping_and_pending_user_is_ineligible():void {
  $admin=User::factory()->create(['is_active'=>true]);$admin->assignRole('super-admin');
  $global=User::factory()->create(['is_active'=>true]);$global->assignRole('global-ra');
  $pending=User::factory()->create(['is_active'=>true,'provisioning_status'=>'pending_review']);$pending->assignRole('local-ra');
  $r=DB::table('regions')->insertGetId(['code'=>'PZ','name'=>'Restricted','assignment_strategy'=>'country_assessor','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $c=DB::table('countries')->insertGetId(['region_id'=>$r,'iso2'=>'PY','iso3'=>'PYY','name'=>'Restricted sample','is_active'=>1,'created_at'=>now(),'updated_at'=>now()]);
  $this->actingAs($global)->post(route('admin.primary-mapping.store'),['country_id'=>$c,'user_id'=>$pending->id])->assertForbidden();
  $this->actingAs($admin)->post(route('admin.primary-mapping.store'),['country_id'=>$c,'user_id'=>$pending->id])->assertSessionHasErrors('user_id');
  $this->assertDatabaseCount('country_assessors',0);
 }
}
