<?php

namespace App\Http\Controllers;

use App\Services\Ccr\ImpactAreaCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CcrImpactController
{
    private function record(Request $request, int $id): object
    {
        $record = DB::table('change_control_records')->where('id', $id)->first();
        abort_if($record === null, 404);
        abort_unless($record->created_by === $request->user()->id || $request->user()->hasRole('super-admin'), 403);
        return $record;
    }

    public function edit(Request $request, int $id, ImpactAreaCatalog $catalog): View
    {
        $record = $this->record($request, $id);
        $decisions = DB::table('ccr_impact_decisions')->where('change_control_record_id', $id)->get()->keyBy('area_code');
        $areas = ImpactAreaCatalog::AREAS;
        $suggested = $catalog->suggested($record->change_type);
        return view('ccr.impact-areas', compact('record', 'decisions', 'areas', 'suggested'));
    }

    public function update(Request $request, int $id, ImpactAreaCatalog $catalog): RedirectResponse
    {
        $record = $this->record($request, $id);
        abort_unless($record->status === 'draft', 409, 'Impact selections are locked after draft stage.');
        $areas = ImpactAreaCatalog::AREAS;
        $suggested = $catalog->suggested($record->change_type);
        $rules = ['decisions' => ['required', 'array', 'size:' . count($areas)]];
        foreach ($areas as $code => $label) {
            $rules['decisions.' . $code] = ['required', Rule::in(['selected', 'not_selected'])];
            $rules['rationales.' . $code] = ['nullable', 'string', 'max:2000'];
        }
        $validated = Validator::make($request->all(), $rules)->after(function ($validator) use ($request, $areas, $suggested, $catalog) {
            $values = $request->input('decisions', []);
            if (!is_array($values) || array_diff(array_keys($values), array_keys($areas))) {
                $validator->errors()->add('decisions', 'Only the 16 defined impact areas may be submitted.');
                return;
            }
            foreach ($suggested as $code) {
                if (
                    $catalog->mustExplainExclusion($code) && ($values[$code] ?? null) === 'not_selected'
                    && trim((string) $request->input('rationales.' . $code, '')) === ''
                ) {
                    $validator->errors()->add('rationales.' . $code, 'A rationale is required when excluding a significant suggested area: ' . ($areas[$code] ?? $code));
                }
            }
        })->validate();
        DB::transaction(function () use ($request, $id, $validated, $areas, $suggested) {
            // Serialize edits to the same CCR; do not change the CCR lifecycle state.
            DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            $now = now('UTC');
            foreach ($areas as $code => $label) {
                $decision = $validated['decisions'][$code];
                $rationale = trim((string) ($validated['rationales'][$code] ?? ''));
                $new = [
                    'decision' => $decision,
                    'rationale' => $rationale ?: null,
                    'suggested_by_rule' => in_array($code, $suggested, true),
                    'decided_by' => $request->user()->id
                ];
                $old = DB::table('ccr_impact_decisions')->where('change_control_record_id', $id)->where('area_code', $code)->first();
                if ($old && $old->decision === $new['decision'] && $old->rationale === $new['rationale'] && (bool) $old->suggested_by_rule === $new['suggested_by_rule']) continue;
                DB::table('ccr_impact_decisions')->updateOrInsert(
                    ['change_control_record_id' => $id, 'area_code' => $code],
                    $new + ['updated_at' => $now, 'created_at' => $old?->created_at ?? $now]
                );
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $request->user()->id,
                    'entity_type' => 'change_control_record',
                    'entity_id' => $id,
                    'action' => 'ccr.impact.decision.saved',
                    'old_values' => $old ? json_encode(['area' => $code, 'decision' => $old->decision, 'rationale' => $old->rationale], JSON_THROW_ON_ERROR) : null,
                    'new_values' => json_encode(['area' => $code, 'decision' => $decision, 'rationale' => $new['rationale'], 'rule_suggested' => $new['suggested_by_rule']], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
        return redirect()->route('ccr.impacts.edit', $id)->with('success', 'Impact area decisions saved. No assessments or approvals were created.');
    }
}
