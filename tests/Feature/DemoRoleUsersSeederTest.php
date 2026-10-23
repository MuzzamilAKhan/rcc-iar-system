<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoRoleUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoRoleUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_five_synthetic_users_with_separate_roles_and_no_imported_accounts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $before = glob(storage_path('app/private/rcc-demo-role-credentials-*.txt')) ?: [];
        try {
            $this->seed(DemoRoleUsersSeeder::class);
            foreach (DemoRoleUsersSeeder::ACCOUNTS as $role => [$name, $email]) {
                $user = User::query()->where('email', $email)->firstOrFail();
                $this->assertTrue($user->is_active);
                $this->assertSame([$role], $user->getRoleNames()->all());
                $this->assertTrue(\Illuminate\Support\Facades\Hash::check('not-a-demo-password', $user->password) === false);
            }
            $this->assertSame(5, User::query()->count());
        } finally {
            $after = glob(storage_path('app/private/rcc-demo-role-credentials-*.txt')) ?: [];
            foreach (array_diff($after, $before) as $file) @unlink($file);
        }
    }
}
