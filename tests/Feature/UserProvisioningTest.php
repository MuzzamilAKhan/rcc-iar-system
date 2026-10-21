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


    private function makePendingSourceFor(User $target): int
    {
        $rowId = \Illuminate\Support\Facades\DB::table('master_import_rows')->insertGetId([
            'source_file' => 'local_ra.csv',
            'source_row' => 10000 + $target->id,
            'record_kind' => 'local_ra',
            'source_fingerprint' => hash('sha256', 'source-verify-' . $target->id),
            'source_data' => json_encode(['Email' => $target->email, 'Title' => 'Demo']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return \Illuminate\Support\Facades\DB::table('imported_user_sources')->insertGetId([
            'user_id' => $target->id,
            'master_import_row_id' => $rowId,
            'source_email' => strtolower(trim($target->email)),
            'verification_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_super_admin_can_verify_source_with_reason_and_audit(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');
        $target = User::factory()->create(['is_active' => false, 'provisioning_status' => 'pending_review']);
        $target->assignRole('local-ra');
        $linkId = $this->makePendingSourceFor($target);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\UserProvisioning::class)
            ->call('selectForApproval', $target->id)
            ->assertSee('local_ra.csv')
            ->call('selectForVerification', $linkId)
            ->set('verificationReason', 'Original source email and identity evidence reviewed.')
            ->call('verifySelectedSource')
            ->assertHasNoErrors()
            ->assertSee('All linked sources verified');

        $this->assertDatabaseHas('imported_user_sources', [
            'id' => $linkId,
            'verification_status' => 'verified',
            'verified_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('user_provisioning_events', [
            'user_id' => $target->id,
            'actor_id' => $admin->id,
            'action' => 'source_verified',
        ]);
        $this->assertFalse($target->fresh()->is_active);
    }

    public function test_invalid_reason_and_cross_user_source_cannot_be_verified(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');
        $first = User::factory()->create(['is_active' => false, 'provisioning_status' => 'pending_review']);
        $first->assignRole('local-ra');
        $other = User::factory()->create(['is_active' => false, 'provisioning_status' => 'pending_review']);
        $other->assignRole('local-ra');
        $firstLink = $this->makePendingSourceFor($first);
        $otherLink = $this->makePendingSourceFor($other);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\UserProvisioning::class)
            ->call('selectForApproval', $first->id)
            ->call('selectForVerification', $firstLink)
            ->set('verificationReason', 'short')
            ->call('verifySelectedSource')
            ->assertHasErrors(['verificationReason' => 'min']);

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Admin\UserProvisioning::class)
            ->set('selectedUserId', $first->id)
            ->set('selectedSourceLinkId', $otherLink)
            ->set('verificationReason', 'Cross account review should be rejected.')
            ->call('verifySelectedSource')
            ->assertHasErrors('source');

        $this->assertDatabaseHas('imported_user_sources', ['id' => $firstLink, 'verification_status' => 'pending']);
        $this->assertDatabaseHas('imported_user_sources', ['id' => $otherLink, 'verification_status' => 'pending']);
    }

    public function test_local_ra_cannot_verify_source(): void
    {
        $localRa = User::factory()->create(['is_active' => true]);
        $localRa->assignRole('local-ra');
        $target = User::factory()->create(['is_active' => false, 'provisioning_status' => 'pending_review']);
        $target->assignRole('global-ra');
        $linkId = $this->makePendingSourceFor($target);
        \Livewire\Livewire::actingAs($localRa)
            ->test(\App\Livewire\Admin\UserProvisioning::class)
            ->assertForbidden();
        $this->assertDatabaseHas('imported_user_sources', ['id' => $linkId, 'verification_status' => 'pending']);
    }

}
