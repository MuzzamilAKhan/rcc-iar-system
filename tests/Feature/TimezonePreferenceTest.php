<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezonePreferenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_active_user_can_select_timezone_and_change_is_audited(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        $this->actingAs($user)->get(route('profile.timezone.edit'))->assertOk();
        $this->actingAs($user)->put(route('profile.timezone.update'), ['timezone' => 'America/Toronto'])
            ->assertRedirect(route('profile.timezone.edit'));
        $this->assertSame('America/Toronto', $user->fresh()->timezone);
        $this->assertDatabaseHas('audit_logs', ['actor_user_id' => $user->id, 'entity_type' => User::class, 'entity_id' => $user->id, 'action' => 'user.timezone.updated']);
    }

    public function test_invalid_timezone_is_rejected_and_user_is_unchanged(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('global-ra');
        $this->actingAs($user)->from(route('profile.timezone.edit'))
            ->put(route('profile.timezone.update'), ['timezone' => 'Invalid/Planet'])
            ->assertSessionHasErrors('timezone');
        $this->assertSame('UTC', $user->fresh()->timezone);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'user.timezone.updated')->count());
    }

    public function test_inactive_user_is_blocked_from_timezone_settings(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('global-ra');
        $this->actingAs($user)->get(route('profile.timezone.edit'))->assertRedirect();
    }
}
