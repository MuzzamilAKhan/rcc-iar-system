<?php

namespace App\Services\Iar;

use App\Models\ImpactAssessment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateIarDraft
{
    public function create(User $actor, array $data): ImpactAssessment
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

        $products = Product::query()->whereIn('id', $validated['product_ids'])->get(['id', 'sap_code']);
        if ($products->count() !== count($validated['product_ids'])) {
            throw ValidationException::withMessages(['product_ids' => 'One or more selected products are unavailable.']);
        }

        if ($products->pluck('sap_code')->unique()->count() !== $products->count()) {
            throw ValidationException::withMessages(['product_ids' => 'Selected products must have distinct SAP codes within one IAR.']);
        }

        return DB::transaction(function () use ($actor, $validated, $products) {
            // Temporary unique number is replaced after the database allocates the unique ID.
            $iar = ImpactAssessment::create([
                'iar_number' => 'TMP-' . Str::uuid(),
                'ccr_number' => trim($validated['ccr_number']),
                'creator_id' => $actor->id,
                'current_version' => trim($validated['current_version']),
                'new_version' => trim($validated['new_version']),
                'sap_udi_icc_impacted' => $validated['sap_udi_icc_impacted'],
                'labelling_impacted' => $validated['labelling_impacted'],
                'is_urgent' => $validated['is_urgent'],
                'estimated_implementation_date' => $validated['estimated_implementation_date'] ?? null,
                'due_at' => $validated['due_at'],
                'status' => 'draft',
            ]);

            $iar->update(['iar_number' => sprintf('IAR-%s-%06d', now()->format('Y'), $iar->id)]);

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
                'action' => 'iar.draft.created',
                'old_values' => null,
                'new_values' => json_encode([
                    'iar_number' => $iar->iar_number,
                    'ccr_number' => $iar->ccr_number,
                    'product_ids' => $products->pluck('id')->all(),
                    'status' => 'draft',
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $iar;
        });
    }
}
