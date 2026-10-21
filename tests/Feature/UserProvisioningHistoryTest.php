<?php

namespace Tests\Feature;

use App\Livewire\Admin\UserProvisioning;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class UserProvisioningHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_can_view_approved_contact_audit_history_without_approval_form(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $target = User::factory()->create([
            'is_active' => false,
            'provisioning_status' => 'approved',
        ]);
        $target->assignRole('local-ra');

        DB::table('user_provisioning_events')->insert([
            'user_id' => $target->id,
            'actor_id' => $admin->id,
            'from_status' => 'pending_review',
            'to_status' => 'approved',
            'action' => 'approved',
            'reason' => 'Independent evidence reviewed for this contact.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(UserProvisioning::class)
            ->call('viewHistory', $target->id)
            ->assertSet('selectedUserId', $target->id)
            ->assertSee('Provisioning Audit History')
            ->assertSee('Independent evidence reviewed for this contact.')
            ->assertSee($admin->name)
            ->assertDontSee('Confirm Approval');
    }

    public function test_local_ra_cannot_open_account_history(): void
    {
        $localRa = User::factory()->create(['is_active' => true]);
        $localRa->assignRole('local-ra');

        // Component render itself must be forbidden to non-admin users.
        Livewire::actingAs($localRa)
            ->test(UserProvisioning::class)
            ->assertForbidden();
    }

    public function test_history_is_scoped_to_selected_account(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $first = User::factory()->create(['is_active' => false, 'provisioning_status' => 'approved']);
        $second = User::factory()->create(['is_active' => false, 'provisioning_status' => 'approved']);

        foreach ([$first, $second] as $target) {
            DB::table('user_provisioning_events')->insert([
                'user_id' => $target->id,
                'actor_id' => $admin->id,
                'from_status' => 'pending_review',
                'to_status' => 'approved',
                'action' => 'approved',
                'reason' => $target->id === $first->id ? 'First contact identity verified.' : 'Second contact identity verified.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Livewire::actingAs($admin)
            ->test(UserProvisioning::class)
            ->call('viewHistory', $first->id)
            ->assertSee('First contact identity verified.')
            ->assertDontSee('Second contact identity verified.');
    }
}
