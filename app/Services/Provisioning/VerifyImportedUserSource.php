<?php

namespace App\Services\Provisioning;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyImportedUserSource
{
    public function verify(
        User $actor,
        int $linkId,
        string $reason
    ): void {
        abort_unless(
            $actor->is_active &&
            $actor->hasRole('super-admin'),
            403
        );

        $reason = trim($reason);

        if (
            mb_strlen($reason) < 10 ||
            mb_strlen($reason) > 1000
        ) {
            throw ValidationException::withMessages([
                'reason' => 'Verification reason must be 10 to 1000 characters.',
            ]);
        }

        DB::transaction(function () use ($actor, $linkId, $reason) {
            $link = DB::table('imported_user_sources')
                ->where('id', $linkId)
                ->lockForUpdate()
                ->first();

            if (!$link) {
                throw ValidationException::withMessages([
                    'source' => 'Source linkage not found.',
                ]);
            }

            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($link->user_id);

            if (
                $user->is($actor) ||
                $user->is_active ||
                $user->provisioning_status !== 'pending_review' ||
                !$user->hasAnyRole(['global-ra', 'local-ra']) ||
                $link->verification_status !== 'pending'
            ) {
                throw ValidationException::withMessages([
                    'source' => 'Source is not eligible for verification.',
                ]);
            }

            $source = DB::table('master_import_rows')
                ->where('id', $link->master_import_row_id)
                ->first();

            if (
                !$source ||
                !in_array(
                    $source->record_kind,
                    ['global_ra', 'local_ra'],
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'source' => 'Invalid import source type.',
                ]);
            }

            $data = json_decode(
                $source->source_data,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            $email = strtolower(trim(
                (string) ($data['Email'] ?? '')
            ));

            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                $email !== strtolower(trim($user->email)) ||
                $email !== $link->source_email
            ) {
                throw ValidationException::withMessages([
                    'source' => 'Source email does not match account identity.',
                ]);
            }

            DB::table('imported_user_sources')
                ->where('id', $linkId)
                ->update([
                    'verification_status' => 'verified',
                    'verified_by' => $actor->id,
                    'verified_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('user_provisioning_events')->insert([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'from_status' => $user->provisioning_status,
                'to_status' => $user->provisioning_status,
                'action' => 'source_verified',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
