<?php

namespace App\Services\Ccr;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Creates a draft CCR only: never authorizes approval, implementation or AI conclusions. */
final class CreateCcr
{
    public function create(User $actor, array $input): int
    {
        abort_unless($actor->is_active && ($actor->can('iar.create') || $actor->hasRole('super-admin')), 403);

        $validated = Validator::make($input, [
            'title' => ['required', 'string', 'min:5', 'max:200'],
            'change_type' => ['required', Rule::in(['design', 'materials', 'manufacturing', 'software', 'labelling', 'supplier', 'other'])],
            'current_state' => ['required', 'string', 'min:10', 'max:10000'],
            'proposed_state' => ['required', 'string', 'min:10', 'max:10000'],
            'reason_for_change' => ['required', 'string', 'min:10', 'max:10000'],
            'affected_scope' => ['nullable', 'string', 'max:5000'],
            'change_duration' => ['required', Rule::in(['permanent', 'temporary'])],
            'target_implementation_date' => ['nullable', 'date', 'after_or_equal:today'],
            'product_ids' => ['required', 'array', 'min:1', 'max:50'],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated) {
            // The database sequence supplies unique IDs; CCR number is derived from the ID
            // inside the same transaction, not from a race-prone MAX or COUNT query.
            $now = now('UTC');
            $id = DB::table('change_control_records')->insertGetId([
                'ccr_number' => 'PENDING-' . bin2hex(random_bytes(12)),
                'created_by' => $actor->id,
                'title' => $validated['title'],
                'change_type' => $validated['change_type'],
                'current_state' => $validated['current_state'],
                'proposed_state' => $validated['proposed_state'],
                'reason_for_change' => $validated['reason_for_change'],
                'affected_scope' => $validated['affected_scope'] ?? null,
                'change_duration' => $validated['change_duration'],
                'target_implementation_date' => $validated['target_implementation_date'] ?? null,
                'status' => 'draft', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $number = sprintf('CCR-%s-%06d', $now->format('Y'), $id);
            DB::table('change_control_records')->where('id', $id)->update(['ccr_number' => $number]);
            foreach ($validated['product_ids'] as $productId) {
                DB::table('ccr_products')->insert([
                    'change_control_record_id' => $id,
                    'product_id' => $productId,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'entity_type' => 'change_control_record',
                'entity_id' => $id,
                'action' => 'ccr.draft.created',
                'old_values' => null,
                'new_values' => json_encode(['ccr_number' => $number, 'status' => 'draft', 'product_ids' => $validated['product_ids']], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
            return $id;
        });
    }
}
