<?php

namespace App\Http\Controllers;

use App\Services\Ccr\AssessmentQuestionCatalog;
use App\Services\Ccr\ImpactAreaCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CCR3B: assignee-owned draft responses only. No submission, approval or AI generation. */
final class CcrSmeDraftController
{
    public function index(Request $request): View
    {
        $assignments = DB::table('ccr_sme_assignments as a')
            ->join('change_control_records as c', 'c.id', '=', 'a.change_control_record_id')
            ->leftJoin('ccr_sme_responses as r', 'r.ccr_sme_assignment_id', '=', 'a.id')
            ->where('a.assigned_to_user_id', $request->user()->id)
            ->select('a.id', 'a.area_code', 'a.status as assignment_status', 'c.ccr_number', 'c.title',
                'c.status as ccr_status', 'r.answer', 'r.updated_at as response_updated_at')
            ->orderByDesc('a.id')->paginate(15);
        $labels = ImpactAreaCatalog::AREAS;
        return view('ccr.my-sme-assessments', compact('assignments', 'labels'));
    }

    private function authorized(Request $request, int $assignmentId): array
    {
        $assignment = DB::table('ccr_sme_assignments as a')
            ->join('change_control_records as c', 'c.id', '=', 'a.change_control_record_id')
            ->where('a.id', $assignmentId)
            ->select('a.*', 'c.ccr_number', 'c.title', 'c.status as ccr_status')
            ->first();
        abort_if($assignment === null, 404);
        abort_unless((int) $assignment->assigned_to_user_id === (int) $request->user()->id, 403);
        abort_unless($request->user()->is_active, 403);
        $selected = DB::table('ccr_impact_decisions')
            ->where('change_control_record_id', $assignment->change_control_record_id)
            ->where('area_code', $assignment->area_code)
            ->where('decision', 'selected')->exists();
        abort_unless($selected, 409, 'Assessment area is no longer applicable.');
        return [$assignment, $selected];
    }

    public function edit(Request $request, int $assignmentId): View
    {
        [$assignment] = $this->authorized($request, $assignmentId);
        $response = DB::table('ccr_sme_responses')->where('ccr_sme_assignment_id', $assignmentId)->first();
        $areaLabel = ImpactAreaCatalog::AREAS[$assignment->area_code] ?? $assignment->area_code;
        $question = AssessmentQuestionCatalog::QUESTIONS[$assignment->area_code] ?? 'Assess the impact of this change.';
        return view('ccr.sme-draft', compact('assignment', 'response', 'areaLabel', 'question'));
    }

    public function save(Request $request, int $assignmentId): RedirectResponse
    {
        [$assignment] = $this->authorized($request, $assignmentId);
        abort_unless($assignment->ccr_status === 'draft', 409, 'CCR is locked for draft edits.');
        abort_unless(in_array($assignment->status, ['assigned','draft','returned'], true), 409, 'Submitted/reviewed assessment is locked.');
        $input = Validator::make($request->all(), [
            'answer' => ['required', Rule::in(['yes', 'no', 'uncertain'])],
            'impact_conclusion' => ['required', Rule::in(['potential_impact', 'no_impact', 'further_assessment'])],
            'rationale' => ['required', 'string', 'min:15', 'max:10000'],
            'evidence_references' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        DB::transaction(function () use ($request, $assignmentId, $assignment, $input) {
            $current = DB::table('ccr_sme_assignments')->where('id', $assignmentId)->lockForUpdate()->first();
            abort_unless($current && (int) $current->assigned_to_user_id === (int) $request->user()->id, 403);
            $ccr = DB::table('change_control_records')->where('id', $assignment->change_control_record_id)->first();
            abort_unless($ccr && $ccr->status === 'draft', 409, 'CCR is locked.');
            abort_unless(in_array($current->status, ['assigned', 'draft', 'returned'], true), 409, 'Submitted/reviewed assessment is locked.');
            abort_unless(DB::table('ccr_impact_decisions')->where('change_control_record_id', $ccr->id)
                ->where('area_code', $current->area_code)->where('decision', 'selected')->exists(), 409);
            $old = DB::table('ccr_sme_responses')->where('ccr_sme_assignment_id', $assignmentId)->first();
            $new = [
                'answer' => $input['answer'], 'impact_conclusion' => $input['impact_conclusion'],
                'rationale' => trim($input['rationale']),
                'evidence_references' => trim($input['evidence_references'] ?? '') ?: null,
            ];
            if ($old && $old->answer === $new['answer'] && $old->impact_conclusion === $new['impact_conclusion']
                && $old->rationale === $new['rationale'] && $old->evidence_references === $new['evidence_references']) return;
            $now = now('UTC');
            DB::table('ccr_sme_responses')->updateOrInsert(
                ['ccr_sme_assignment_id' => $assignmentId],
                $new + ['created_by_user_id' => $request->user()->id, 'updated_at' => $now,
                    'created_at' => $old?->created_at ?? $now]
            );
            DB::table('ccr_sme_assignments')->where('id', $assignmentId)
                ->update(['status' => 'draft', 'updated_at' => $now]);
            // Audit metadata only; response evidence and rationale remain restricted to assigned SME.
            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record',
                'entity_id' => $ccr->id, 'action' => 'ccr.sme.draft.saved',
                'old_values' => $old ? json_encode(['assignment_id' => $assignmentId, 'answer' => $old->answer,
                    'impact_conclusion' => $old->impact_conclusion], JSON_THROW_ON_ERROR) : null,
                'new_values' => json_encode(['assignment_id' => $assignmentId, 'area_code' => $current->area_code,
                    'answer' => $new['answer'], 'impact_conclusion' => $new['impact_conclusion']], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('ccr.sme-drafts.edit', $assignmentId)
            ->with('success', 'SME draft saved. No assessment submitted or approved.');
    }
}
