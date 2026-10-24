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
            'product_ids' => ['nullable', 'array', 'max:10000'],
            'bulk_sap_input' => ['nullable', 'string', 'max:1100000'],
            'override_warnings' => ['nullable', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:2000'],
            'product_ids.*' => ['required', 'integer', 'distinct'],
        ])->validate();

        $bulk = trim((string) ($validated['bulk_sap_input'] ?? ''));
        $bulkResolution = null;
                if ($bulk !== '') {
                    $bulkResolution = app(ResolveSapDraftExceptions::class)->resolve(
                        $bulk, (bool) ($validated['override_warnings'] ?? false), $validated['override_reason'] ?? null
                    );
                    $products = collect($bulkResolution['rows'])->map(fn ($row) => (object) [
                        'id' => $row['product_id'], 'sap_code' => $row['submitted_sap_code'],
                    ]);
                } else {
                    $ids = $validated['product_ids'] ?? [];
                    if (!$ids) {
                        throw ValidationException::withMessages(['product_ids' => 'Select products or paste SAP codes first.']);
                    }
                    $products = collect();
                    foreach (array_chunk($ids, 400) as $chunk) {
                        $products = $products->concat(Product::query()->whereIn('id', $chunk)->get(['id', 'sap_code']));
                    }
                }
        if (($bulk === '' && $products->count() !== count($validated['product_ids'] ?? []))) {
            throw ValidationException::withMessages(['product_ids' => 'One or more selected products are unavailable.']);
        }

        if ($products->pluck('sap_code')->unique()->count() !== $products->count()) {
            throw ValidationException::withMessages(['product_ids' => 'Selected products must have distinct SAP codes within one IAR.']);
        }

        return DB::transaction(function () use ($actor, $validated, $products, $bulkResolution) {
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

            $now = now();
            foreach ($products->chunk(400) as $chunk) {
                $rows = $chunk->map(fn ($product) => [
                    'impact_assessment_id' => $iar->id,
                    'product_id' => $product->id,
                    'submitted_sap_code' => $product->sap_code,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();
                DB::table('iar_products')->insert($rows);
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
                    'unresolved_sap_codes' => $bulkResolution['unresolved'] ?? [],
                    'override_reason' => $bulkResolution['reason'] ?? null,
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($bulkResolution && $bulkResolution['unresolved']) {
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $actor->id, 'entity_type' => ImpactAssessment::class,
                    'entity_id' => $iar->id, 'action' => 'iar.sap.override.created',
                    'old_values' => null,
                    'new_values' => json_encode(['unresolved_sap_codes' => $bulkResolution['unresolved'],
                        'reason' => $bulkResolution['reason'], 'submission_allowed' => false], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            return $iar;
        });
    }
}
