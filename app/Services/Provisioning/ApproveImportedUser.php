<?php

namespace App\Services\Provisioning;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApproveImportedUser
{
    public function approve(User $actor, int $targetUserId, string $reason = 'Approved for secure invitation'): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'Approval reason must be 10 to 1000 characters.',
            ]);
        }
        if (
            !$actor->is_active ||
            !$actor->hasRole('super-admin')
        ) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $targetUserId, $reason) {
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
                ->whereRaw(
                    'LOWER(json_extract(source_data, ?)) = ?',
                    ['$.Email', strtolower(trim($user->email))]
                )
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
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
