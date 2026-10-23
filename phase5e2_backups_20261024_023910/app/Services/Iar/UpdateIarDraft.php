<?php

namespace App\Services\Iar;

use App\Models\ImpactAssessment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateIarDraft
{
    public function update(User $actor, int $iarId, array $data): ImpactAssessment
    {
        abort_unless($actor->is_active && $actor->can('iar.create'), 403);

        $validated = validator($data, [
            'ccr_number' => ['required', 'string', 'max:100'],
            'current_version' => ['required', 'string', 'max:10000'],
            'new_version' => ['required', 'string', 'max:10000'],
            'sap_udi_icc_impacted' => ['required', 'boolean'],
            'labelling_impacted' => ['required', 'boolean'],
            'is_urgent' => ['required', 'boolean'],
            'estimated_implementation_date' => ['nullable', 'date'],
            'due_at' => ['required', 'date', 'after:now'],
            'product_ids' => ['required', 'array', 'min:1', 'max:30'],
            'product_ids.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
        ])->validate();

        return DB::transaction(function () use ($actor, $iarId, $validated) {
            $iar = ImpactAssessment::query()->lockForUpdate()->findOrFail($iarId);
            abort_unless($iar->creator_id === $actor->id, 403);
            if ($iar->status !== 'draft') {
                throw ValidationException::withMessages(['draft' => 'Only draft IARs may be edited.']);
            }

            $products = Product::query()->whereIn('id', $validated['product_ids'])->get(['id', 'sap_code']);
            if ($products->count() !== count($validated['product_ids']) ||
                $products->pluck('sap_code')->unique()->count() !== $products->count()) {
                throw ValidationException::withMessages(['product_ids' => 'Select available products with distinct SAP codes.']);
            }

            $fields = [
                'ccr_number' => trim($validated['ccr_number']),
                'current_version' => trim($validated['current_version']),
                'new_version' => trim($validated['new_version']),
                'sap_udi_icc_impacted' => $validated['sap_udi_icc_impacted'],
                'labelling_impacted' => $validated['labelling_impacted'],
                'is_urgent' => $validated['is_urgent'],
                'estimated_implementation_date' => $validated['estimated_implementation_date'] ?? null,
                'due_at' => $validated['due_at'],
            ];

            $old = $iar->only(array_keys($fields));
            $old['product_ids'] = $iar->products()->orderBy('product_id')->pluck('product_id')->map(fn ($id) => (int) $id)->all();

            $iar->update($fields);
            $iar->products()->delete();
            foreach ($products as $product) {
                $iar->products()->create([
                    'product_id' => $product->id,
                    'submitted_sap_code' => $product->sap_code,
                ]);
            }

            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id,
                'entity_type' => ImpactAssessment::class,
                'entity_id' => $iar->id,
                'action' => 'iar.draft.updated',
                'old_values' => json_encode($old, JSON_THROW_ON_ERROR),
                'new_values' => json_encode($fields + ['product_ids' => $products->pluck('id')->sort()->values()->all()], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $iar->refresh();
        });
    }
}
