<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Explicit demo-only seeder. Never add to DatabaseSeeder or normal production seed flows.
 * Uses reserved .invalid addresses; never contacts or activates imported RA accounts.
 */
class DemoRoleUsersSeeder extends Seeder
{
    public const ACCOUNTS = [
        'super-admin' => ['Demo System Administrator', 'demo.admin@rcc-iar.invalid'],
        'global-ra' => ['Demo Global RA', 'demo.global-ra@rcc-iar.invalid'],
        'local-ra' => ['Demo Local RA', 'demo.local-ra@rcc-iar.invalid'],
        'master-data-manager' => ['Demo Master Data Manager', 'demo.master-data@rcc-iar.invalid'],
        'read-only-reviewer' => ['Demo Read-Only Reviewer', 'demo.reviewer@rcc-iar.invalid'],
    ];

    public function run(): void
    {
        // Never replace an existing account or its roles/password. Explicit conflict detection.
        foreach (self::ACCOUNTS as [$name, $email]) {
            if (User::query()->where('email', $email)->exists()) {
                throw new RuntimeException("Demo email already exists: {$email}. Nothing changed. Do not overwrite existing users; check the account before retrying.");
            }
        }
        foreach (array_keys(self::ACCOUNTS) as $role) {
            if (!\Spatie\Permission\Models\Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
                throw new RuntimeException("Missing role '{$role}'. Run the existing RolesAndPermissionsSeeder first.");
            }
        }

        $path = storage_path('app/private');
        File::ensureDirectoryExists($path, 0700, true);
        $filename = $path . DIRECTORY_SEPARATOR . 'rcc-demo-role-credentials-' . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6)) . '.txt';
        $secrets = [];
        $created = [];
        // The credentials are NEVER committed, emailed or written into application logs.
        DB::transaction(function () use (&$secrets, &$created, $filename) {
            foreach (self::ACCOUNTS as $role => [$name, $email]) {
                $password = bin2hex(random_bytes(16));
                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password, // User model hashed cast.
                    'is_active' => true,
                ]);
                $user->assignRole($role);
                $created[] = $user;
                $secrets[] = "Role: {$role}\nEmail: {$email}\nTemporary password: {$password}\n";
            }
            $content = "RCC-IAR DEMO CREDENTIALS - CONFIDENTIAL\n" .
                "Generated: " . now()->toIso8601String() . "\n" .
                "DEMO ONLY. Share each role's credentials privately. Rotate/disable after UAT.\n" .
                "URL: http://194.163.144.10/rcc-iar-system/public/\n" .
                "WARNING: HTTP is unencrypted. Do NOT use real corporate passwords/data.\n\n" .
                implode("\n", $secrets);
            if (file_put_contents($filename, $content, LOCK_EX) === false) {
                throw new RuntimeException('Could not write private credential file; database transaction rolled back.');
            }
            @chmod($filename, 0600);
        });

        $this->command?->info(count($created) . ' synthetic demo role users created. No emails sent.');
        $this->command?->warn('Private credential file: ' . $filename);
        $this->command?->warn('Do not put this file in Git, public/, chat, or email it in bulk.');
    }
}
