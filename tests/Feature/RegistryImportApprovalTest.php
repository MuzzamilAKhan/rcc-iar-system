<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Country;
use App\Services\RegistryImport\RegistryImportWorkflow;
use App\Services\RegistryImport\RegistryImportParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegistryImportApprovalTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp():void {parent::setUp();$this->seed(RolesAndPermissionsSeeder::class);}
    private function actor(string $role):User {$u=User::factory()->create(['is_active'=>true]);$u->assignRole($role);return $u;}
    private function importFile():UploadedFile {
        $f=tempnam(sys_get_temp_dir(),'registry-import-');
        file_put_contents($f,"SAP Code,Country of Registration,Registration Status,Registration Number\nTEST-001,Germany,active,TEST-REG-01\n");
        return new UploadedFile($f,'test-registry.csv','text/csv',null,true);
    }
    public function test_submit_is_pending_and_only_independent_admin_can_approve():void
    {
        $region=\App\Models\Region::create(['name'=>'Test Europe','code'=>'TEST-EU']);
        $country=Country::create(['name'=>'Germany','iso2'=>'DE','iso3'=>'DEU','region_id'=>$region->id]);
        $product=Product::create(['sap_code'=>'TEST-001','product_name'=>'Synthetic test product']);
        $manager=$this->actor('master-data-manager');$reviewer=$this->actor('super-admin');
        $f=$this->importFile();
        try {$id=app(RegistryImportWorkflow::class)->submit($manager,$f,'Controlled synthetic registration test',app(RegistryImportParser::class));}
        finally {@unlink($f->getRealPath());}
        $this->assertSame(0,DB::table('product_registrations')->count());
        $this->assertDatabaseHas('registry_import_requests',['id'=>$id,'status'=>'pending']);
        $this->actingAs($manager)->post(route('admin.products.import.requests.decide',$id),['decision'=>'approve','review_reason'=>'Independent approval for test'])->assertForbidden();
        $this->actingAs($reviewer)->post(route('admin.products.import.requests.decide',$id),['decision'=>'approve','review_reason'=>'Independent approval for test'])->assertRedirect();
        $this->assertDatabaseHas('product_registrations',['product_id'=>$product->id,'country_id'=>$country->id,'registration_number'=>'TEST-REG-01']);
        $this->assertDatabaseHas('registry_import_requests',['id'=>$id,'status'=>'approved']);
    }
    public function test_uploader_cannot_approve_own_request():void
    {
        $region=\App\Models\Region::create(['name'=>'Test Europe','code'=>'TEST-EU']);
        Country::create(['name'=>'Germany','iso2'=>'DE','iso3'=>'DEU','region_id'=>$region->id]);Product::create(['sap_code'=>'TEST-001','product_name'=>'Synthetic']);
        $admin=$this->actor('super-admin');$f=$this->importFile();
        try {$id=app(RegistryImportWorkflow::class)->submit($admin,$f,'Regulatory registration validation',app(RegistryImportParser::class));}
        finally {@unlink($f->getRealPath());}
        $this->actingAs($admin)->post(route('admin.products.import.requests.decide',$id),['decision'=>'approve','review_reason'=>'Self approval is forbidden'])->assertForbidden();
        $this->assertDatabaseHas('registry_import_requests',['id'=>$id,'status'=>'pending']);
        $this->assertDatabaseCount('product_registrations',0);
    }
}
