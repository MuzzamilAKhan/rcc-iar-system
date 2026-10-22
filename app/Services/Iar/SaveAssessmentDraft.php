<?php

namespace App\Services\Iar;

use App\Models\AssessmentResponse;
use App\Models\IarAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAssessmentDraft
{
    public function save(User $actor, IarAssignment $assignment, array $data): AssessmentResponse
    {
        abort_unless($actor->is_active && $actor->can('assessment.edit.assigned') &&
            (int) $assignment->assigned_to_user_id === (int) $actor->id, 403);

        $values = validator($data, [
            'impact_type' => ['nullable', 'string', 'max:40'],
            'timeline' => ['nullable', 'string', 'max:10000'],
            'action_items' => ['nullable', 'string', 'max:50000'],
            'comments' => ['nullable', 'string', 'max:50000'],
            'stop_ship_required' => ['nullable', 'boolean'],
            'more_information_required' => ['nullable', 'boolean'],
            'changed_product_imported' => ['nullable', 'boolean'],
        ])->validate();

        return DB::transaction(function () use ($actor, $assignment, $values) {
            $locked = IarAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $locked->assigned_to_user_id === (int) $actor->id, 403);

            if ($locked->status !== 'assigned' || $locked->impactAssessment?->status !== 'submitted') {
                throw ValidationException::withMessages(['assessment' => 'This assignment is not open for draft editing.']);
            }

            $response = AssessmentResponse::query()->where('iar_assignment_id', $locked->id)->first();
            if ($response && $response->status !== 'draft') {
                throw ValidationException::withMessages(['assessment' => 'Submitted assessment cannot be edited.']);
            }

            $old = $response?->only(array_keys($values)) ?? null;
            $payload = array_merge([
                'impact_type' => null,
                'timeline' => null,
                'action_items' => null,
                'comments' => null,
                'stop_ship_required' => null,
                'more_information_required' => null,
                'changed_product_imported' => null,
            ], $values);
            foreach (['impact_type', 'timeline', 'action_items', 'comments'] as $field) {
                if (isset($payload[$field])) $payload[$field] = trim($payload[$field]) ?: null;
            }
            $payload['status'] = 'draft';
            $payload['submitted_at'] = null;

            if ($response) {
                $response->update($payload);
            } else {
                $response = AssessmentResponse::query()->create(['iar_assignment_id' => $locked->id] + $payload);
            }

            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'entity_type' => AssessmentResponse::class,
                'entity_id' => $response->id,
                'action' => $old === null ? 'assessment.draft.created' : 'assessment.draft.updated',
                'old_values' => $old === null ? null : json_encode($old, JSON_THROW_ON_ERROR),
                'new_values' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $response->fresh();
        }, 3);
    }
}
