<?php

namespace App\Services\Provisioning;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveImportedUser
{
    public function approve(User $actor, int $targetUserId): void
    {
        if (
            !$actor->is_active ||
            !$actor->hasRole('super-admin')
        ) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $targetUserId) {
            $user = User::query()
                ->lockForUpdate()
                ->findOrFail($targetUserId);

            if (
                $user->is($actor) ||
                $user->is_active ||
                $user->provisioning_status !== 'pending_review' ||
                !$user->hasAnyRole(['global-ra', 'local-ra'])
            ) {
                throw ValidationException::withMessages([
                    'user' => 'Account is not eligible for approval.',
                ]);
            }

            // Only users imported through the master-data process.
            $isImported = DB::table('master_import_rows')
                ->whereIn('record_kind', ['global_ra', 'local_ra'])
                ->where('source_data', 'like', '%' . addcslashes($user->email, '%_\\') . '%')
                ->exists();

            if (!$isImported) {
                throw ValidationException::withMessages([
                    'user' => 'Imported identity could not be confirmed.',
                ]);
            }

            $previous = $user->provisioning_status;

            $user->forceFill([
                'provisioning_status' => 'approved',
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ])->save();

            DB::table('user_provisioning_events')->insert([
                'user_id' => $user->id,
                'actor_id' => $actor->id,
                'from_status' => $previous,
                'to_status' => 'approved',
                'action' => 'approved',
                'reason' => 'Approved for secure invitation',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
