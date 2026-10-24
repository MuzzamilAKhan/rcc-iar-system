<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegAtlasEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_signed_in_user_root_redirects_to_dashboard(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
    }

    public function test_login_page_uses_regatlas_brand_and_existing_auth_form(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('Sign in to RegAtlas')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('name="_token"', false);
    }
}
