<?php
namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistryImportHistoryTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void { parent::setUp(); $this->seed(RolesAndPermissionsSeeder::class); }

    public function test_history_filter_and_evidence_export_are_authorized_and_audited(): void
    {
        $manager = User::factory()->create(['is_active' => true]); $manager->assignRole('master-data-manager');
        $local = User::factory()->create(['is_active' => true]); $local->assignRole('local-ra');
        DB::table('registry_import_requests')->insert([
            'id'=>100, 'uploaded_by'=>$manager->id, 'filename'=>'SYNTHETIC.csv', 'file_sha256'=>str_repeat('a', 64),
            'status'=>'rejected', 'submission_reason'=>'Synthetic request for audit testing', 'review_reason'=>'Synthetic review rejection',
            'total_rows'=>1, 'created_at'=>now(), 'updated_at'=>now(),
        ]);
        $this->actingAs($local)->get(route('admin.products.import.evidence'))->assertForbidden();
        $this->actingAs($manager)->get(route('admin.products.import.requests', ['status'=>'rejected']))->assertOk()->assertSee('SYNTHETIC.csv');
        $this->actingAs($manager)->get(route('admin.products.import.requests', ['status'=>'approved']))->assertOk()->assertDontSee('SYNTHETIC.csv');
        $this->actingAs($manager)->get(route('admin.products.import.evidence', ['status'=>'rejected']))->assertOk()->assertDownload();
        $this->assertDatabaseHas('audit_logs', ['actor_user_id'=>$manager->id, 'action'=>'registry_import.evidence_exported']);
    }
}
