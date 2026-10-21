<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    public function test_super_admin_can_view_users(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('Account Provisioning');
    }

    public function test_master_data_manager_cannot_view_users(): void
    {
        $manager = User::factory()->create(['is_active' => true]);
        $manager->assignRole('master-data-manager');

        $this->actingAs($manager)
            ->get('/admin/users')
            ->assertForbidden();
    }

    public function test_guest_cannot_view_users(): void
    {
        $this->get('/admin/users')
            ->assertRedirect('/login');
    }


    public function test_local_ra_cannot_approve_account_through_livewire(): void
    {
        $localRa = User::factory()->create([
            'is_active' => true,
        ]);

        $localRa->assignRole('local-ra');

        $target = User::factory()->create([
            'is_active' => false,
            'provisioning_status' => 'pending_review',
        ]);

        $target->assignRole('global-ra');

        \Livewire\Livewire::actingAs($localRa)
            ->test(\App\Livewire\Admin\UserProvisioning::class)
            ->call('selectForApproval', $target->id)
            ->assertForbidden();

        $this->assertSame(
            'pending_review',
            $target->fresh()->provisioning_status
        );
    }


    public function test_super_admin_can_approve_imported_user_through_livewire(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
        ]);

        $admin->assignRole('super-admin');

        $target = User::factory()->create([
            'email' => 'approved-contact@example.test',
            'is_active' => false,
            'provisioning_status' => 'pending_review',
        ]);

        $target->assignRole('local-ra');

        \Illuminate\Support\Facades\DB::table('master_import_rows')->insert([
            'source_file' => 'local_ra.csv',
            'source_row' => 999,
            'record_kind' => 'local_ra',
            'source_fingerprint' => hash('sha256', 'approval-ui-test'),
            'source_data' => json_encode([
                'Email' => $target->email,
                'Title' => 'Afghanistan',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);


        $sourceId = \Illuminate\Support\Facades\DB::table(
            'master_import_rows'
        )
            ->where('source_file', 'local_ra.csv')
            ->where('source_row', 999)
            ->value('id');

        $linkId = \Illuminate\Support\Facades\DB::table(
            'imported_user_sources'
        )->insertGetId([
                    'user_id' => $target->id,
                    'master_import_row_id' => $sourceId,
                    'source_email' => strtolower($target->email),
                    'verification_status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

        app(\App\Services\Provisioning\VerifyImportedUserSource::class)
            ->verify(
                $admin,
                $linkId,
                'Contact identity and regulatory role independently reviewed.'
            );


        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\UserProvisioning::class)
            ->call('selectForApproval', $target->id)
            ->set(
                'approvalReason',
                'Identity and Local RA role verified by administrator.'
            )
            ->call('approveSelected')
            ->assertHasNoErrors();

        $target->refresh();

        $this->assertSame('approved', $target->provisioning_status);
        $this->assertFalse($target->is_active);

        $this->assertDatabaseHas('user_provisioning_events', [
            'user_id' => $target->id,
            'actor_id' => $admin->id,
            'action' => 'approved',
            'reason' => 'Identity and Local RA role verified by administrator.',
        ]);
    }


}
