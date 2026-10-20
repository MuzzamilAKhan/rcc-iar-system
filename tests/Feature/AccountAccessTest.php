<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_active_user_can_access_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_inactive_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login_with_correct_password(): void
    {
        $user = User::factory()->create([
            'email' => 'disabled@example.test',
            'password' => Hash::make('TestPassword123!'),
            'is_active' => false,
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'TestPassword123!',
        ])->assertSessionHasErrors();

        $this->assertGuest();
    }
}
