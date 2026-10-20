<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Provisioning\ApproveImportedUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApproveImportedUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            \Database\Seeders\RolesAndPermissionsSeeder::class
        );
    }

    private function createImportedContact(): User
    {
        $user = User::factory()->create([
            'email' => 'imported@example.test',
            'is_active' => false,
            'provisioning_status' => 'pending_review',
        ]);

        $user->assignRole('local-ra');

        DB::table('master_import_rows')->insert([
            'source_file' => 'local_ra.csv',
            'source_row' => 2,
            'record_kind' => 'local_ra',
            'source_fingerprint' => hash('sha256', 'local-ra-test'),
            'source_data' => json_encode([
                'Email' => $user->email,
                'Title' => 'Afghanistan',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    public function test_super_admin_can_approve_imported_contact(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $contact = $this->createImportedContact();

        app(ApproveImportedUser::class)->approve(
            $admin,
            $contact->id
        );

        $contact->refresh();

        $this->assertSame(
            'approved',
            $contact->provisioning_status
        );

        $this->assertFalse($contact->is_active);

        $this->assertDatabaseHas('user_provisioning_events', [
            'user_id' => $contact->id,
            'actor_id' => $admin->id,
            'action' => 'approved',
        ]);
    }

    public function test_local_ra_cannot_approve_users(): void
    {
        $contact = $this->createImportedContact();

        $this->expectException(
            \Symfony\Component\HttpKernel\Exception\HttpException::class
        );

        app(ApproveImportedUser::class)->approve(
            $contact,
            $contact->id
        );
    }

    public function test_unimported_user_cannot_be_approved(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $user = User::factory()->create([
            'is_active' => false,
            'provisioning_status' => 'pending_review',
        ]);

        $user->assignRole('local-ra');

        $this->expectException(ValidationException::class);

        app(ApproveImportedUser::class)->approve(
            $admin,
            $user->id
        );
    }

    public function test_approved_user_cannot_be_approved_again(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('super-admin');

        $contact = $this->createImportedContact();

        app(ApproveImportedUser::class)->approve(
            $admin,
            $contact->id
        );

        $this->expectException(ValidationException::class);

        app(ApproveImportedUser::class)->approve(
            $admin,
            $contact->id
        );
    }
}
