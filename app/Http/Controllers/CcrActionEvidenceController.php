<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/** CCR5C: pre-implementation evidence-package review, NOT action completion or authorization. */
final class CcrActionEvidenceController
{
    private function context(int $actionId): object
    {
        $action = DB::table('ccr_actions as a')
            ->join('change_control_records as c', 'c.id', '=', 'a.change_control_record_id')
            ->where('a.id', $actionId)
            ->select('a.*', 'c.ccr_number', 'c.title as ccr_title', 'c.status as ccr_status')
            ->first();
        abort_if(!$action, 404);
        return $action;
    }

    private function owner(Request $request, int $actionId): object
    {
        $action = $this->context($actionId);
        abort_unless((int) $action->owner_user_id === (int) $request->user()->id, 403);
        return $action;
    }

    private function reviewer(Request $request, int $actionId): object
    {
        $action = $this->context($actionId);
        abort_unless($request->user()->hasRole('super-admin'), 403);
        abort_if((int) $action->owner_user_id === (int) $request->user()->id, 403, 'Self review not allowed.');
        return $action;
    }

    private function reviewRow(int $actionId): ?object
    {
        return DB::table('ccr_action_evidence_reviews')->where('action_id', $actionId)->first();
    }

    public function edit(Request $request, int $actionId): View
    {
        $action = $this->owner($request, $actionId);
        $evidence = $this->reviewRow($actionId);
        return view('ccr.action-evidence', compact('action', 'evidence'));
    }

    public function save(Request $request, int $actionId): RedirectResponse
    {
        $action = $this->owner($request, $actionId);
        abort_unless($action->ccr_status === 'draft' && $action->status === 'proposed', 409);
        $data = Validator::make($request->all(), [
            'owner_explanation' => ['required', 'string', 'min:20', 'max:10000'],
            'evidence_reference' => ['required', 'string', 'min:2', 'max:150'],
            'evidence_revision' => ['nullable', 'string', 'max:80'],
        ])->validate();
        DB::transaction(function () use ($actionId, $data, $request) {
            $action = DB::table('ccr_actions')->where('id', $actionId)->lockForUpdate()->first();
            $ccr = DB::table('change_control_records')->where('id', $action->change_control_record_id)->first();
            abort_unless($ccr && $ccr->status === 'draft' && $action->status === 'proposed', 409);
            $old = $this->reviewRow($actionId);
            abort_if($old && !in_array($old->status, ['draft', 'returned'], true), 409, 'Evidence is locked.');
            $new = [
                'owner_explanation' => trim($data['owner_explanation']),
                'evidence_reference' => trim($data['evidence_reference']),
                'evidence_revision' => trim($data['evidence_revision'] ?? '') ?: null,
            ];
            if ($old && $old->owner_explanation === $new['owner_explanation'] &&
                $old->evidence_reference === $new['evidence_reference'] &&
                $old->evidence_revision === $new['evidence_revision']) { return; }
            $now = now('UTC');
            DB::table('ccr_action_evidence_reviews')->updateOrInsert(['action_id' => $actionId], $new + [
                'status' => 'draft', 'submitted_by_user_id' => null, 'submitted_at' => null,
                'reviewed_by_user_id' => null, 'reviewed_at' => null, 'review_comment' => null,
                'updated_at' => $now, 'created_at' => $old?->created_at ?? $now,
            ]);
            $this->audit($request, $action->change_control_record_id, 'ccr.action.evidence.draft.saved',
                ['action_id' => $actionId, 'evidence_reference' => $new['evidence_reference']]);
        });
        return redirect()->route('ccr.action-evidence.edit', $actionId)->with('success', 'Evidence preparation draft saved. Action remains PROPOSED.');
    }

    public function submit(Request $request, int $actionId): RedirectResponse
    {
        $action = $this->owner($request, $actionId);
        DB::transaction(function () use ($actionId, $request, $action) {
            $row = DB::table('ccr_action_evidence_reviews')->where('action_id', $actionId)->lockForUpdate()->first();
            $current = DB::table('ccr_actions')->where('id', $actionId)->first();
            $ccr = DB::table('change_control_records')->where('id', $action->change_control_record_id)->first();
            abort_unless($ccr && $ccr->status === 'draft' && $current && $current->status === 'proposed', 409);
            abort_unless($row && in_array($row->status, ['draft', 'returned'], true)
                && filled($row->owner_explanation) && filled($row->evidence_reference), 409, 'Save evidence draft first.');
            $now = now('UTC');
            DB::table('ccr_action_evidence_reviews')->where('id', $row->id)->update([
                'status' => 'submitted', 'submitted_by_user_id' => $request->user()->id, 'submitted_at' => $now,
                'reviewed_by_user_id' => null, 'reviewed_at' => null, 'review_comment' => null, 'updated_at' => $now,
            ]);
            $this->audit($request, $action->change_control_record_id, 'ccr.action.evidence.submitted', ['action_id' => $actionId]);
        });
        return redirect()->route('ccr.action-evidence.edit', $actionId)->with('success', 'Evidence package submitted for independent review. No implementation or action completion authorized.');
    }

    public function queue(Request $request): View
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $items = DB::table('ccr_action_evidence_reviews as e')
            ->join('ccr_actions as a', 'a.id', '=', 'e.action_id')
            ->join('change_control_records as c', 'c.id', '=', 'a.change_control_record_id')
            ->join('users as u', 'u.id', '=', 'a.owner_user_id')
            ->whereIn('e.status', ['submitted', 'returned', 'reviewed'])->orderByDesc('e.updated_at')
            ->select('e.action_id', 'e.status', 'a.title', 'c.ccr_number', 'u.name as owner_name')
            ->paginate(20);
        return view('ccr.action-evidence-queue', compact('items'));
    }

    public function show(Request $request, int $actionId): View
    {
        $action = $this->reviewer($request, $actionId);
        $evidence = $this->reviewRow($actionId);
        abort_if(!$evidence, 404);
        return view('ccr.action-evidence-review', compact('action', 'evidence'));
    }

    public function decide(Request $request, int $actionId): RedirectResponse
    {
        $action = $this->reviewer($request, $actionId);
        $data = Validator::make($request->all(), [
            'decision' => ['required', 'in:reviewed,returned'],
            'review_comment' => ['nullable', 'string', 'max:10000'],
        ])->validate();
        if ($data['decision'] === 'returned') {
            Validator::make($request->all(), ['review_comment' => ['required','string','min:15','max:10000']])->validate();
        }
        DB::transaction(function () use ($request, $actionId, $action, $data) {
            $row = DB::table('ccr_action_evidence_reviews')->where('action_id', $actionId)->lockForUpdate()->first();
            $ccr = DB::table('change_control_records')->where('id', $action->change_control_record_id)->first();
            abort_unless($ccr && $ccr->status === 'draft' && $action->status === 'proposed', 409);
            abort_unless($row && $row->status === 'submitted', 409, 'Only submitted evidence can be reviewed.');
            abort_if((int) $row->submitted_by_user_id === (int) $request->user()->id, 403);
            $now = now('UTC');
            DB::table('ccr_action_evidence_reviews')->where('id', $row->id)->update([
                'status' => $data['decision'], 'review_comment' => trim($data['review_comment'] ?? '') ?: null,
                'reviewed_by_user_id' => $request->user()->id, 'reviewed_at' => $now, 'updated_at' => $now,
            ]);
            $this->audit($request, $action->change_control_record_id, 'ccr.action.evidence.'.$data['decision'],
                ['action_id' => $actionId, 'status' => $data['decision']]);
        });
        return redirect()->route('ccr.action-evidence.review', $actionId)
            ->with('success', 'Evidence review recorded; action remains PROPOSED. Not an implementation authorization.');
    }

    private function audit(Request $request, int $ccrId, string $name, array $data): void
    {
        $now = now('UTC');
        DB::table('audit_logs')->insert([
            'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record', 'entity_id' => $ccrId,
            'action' => $name, 'old_values' => null, 'new_values' => json_encode($data, JSON_THROW_ON_ERROR),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
}
