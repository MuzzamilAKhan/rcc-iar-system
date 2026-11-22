<?php

namespace App\Http\Controllers;

use App\Services\Ccr\ImpactAreaCatalog;
use App\Services\Ccr\AssessmentQuestionCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class CcrSmeAssignmentController
{
    private function record(Request $request, int $id): object
    {
        $record = DB::table('change_control_records')->where('id', $id)->first();
        abort_if($record === null, 404);
        abort_unless($record->created_by === $request->user()->id || $request->user()->hasRole('super-admin'), 403);
        return $record;
    }

    public function index(Request $request, int $id): View
    {
        $record = $this->record($request, $id);
        $selected = DB::table('ccr_impact_decisions')
            ->where('change_control_record_id', $id)->where('decision', 'selected')
            ->pluck('area_code')->all();
        $areas = array_intersect_key(ImpactAreaCatalog::AREAS, array_flip($selected));
        $assignments = DB::table('ccr_sme_assignments as a')
            ->join('users as u', 'u.id', '=', 'a.assigned_to_user_id')
            ->where('a.change_control_record_id', $id)
            ->select('a.*', 'u.name as assessor_name')->get()->keyBy('area_code');
        $users = \App\Models\User::query()->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['super-admin', 'global-ra', 'local-ra']))
            ->orderBy('name')->get(['id', 'name']);
        $questions = AssessmentQuestionCatalog::QUESTIONS;
        return view('ccr.sme-assignments', compact('record', 'areas', 'assignments', 'users', 'questions'));
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $record = $this->record($request, $id);
        abort_unless($record->status === 'draft', 409, 'CCR is locked for assignment changes.');
        $data = Validator::make($request->all(), [
            'area_code' => ['required', 'string', Rule::in(array_keys(ImpactAreaCatalog::AREAS))],
            'assigned_to_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ])->validate();
        $area = $data['area_code'];
        $assessorId = (int) $data['assigned_to_user_id'];
        $assessor = \App\Models\User::findOrFail($assessorId);
        abort_unless($assessor->is_active && $assessor->hasAnyRole(['super-admin', 'global-ra', 'local-ra']), 422, 'Assessor must be an active RA user.');

        DB::transaction(function () use ($request, $id, $area, $assessorId) {
            $locked = DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($locked && $locked->status === 'draft', 409);
            $selected = DB::table('ccr_impact_decisions')->where('change_control_record_id', $id)
                ->where('area_code', $area)->where('decision', 'selected')->exists();
            abort_unless($selected, 422, 'Only applicable selected areas can be assigned.');
            $existing = DB::table('ccr_sme_assignments')->where('change_control_record_id', $id)
                ->where('area_code', $area)->first();
            // No silent reassignment. Dedicated audited reassignment workflow comes later.
            abort_if($existing && (int) $existing->assigned_to_user_id !== $assessorId, 409, 'Area already assigned. Reassignment is not enabled.');
            if ($existing) return;
            $now = now('UTC');
            DB::table('ccr_sme_assignments')->insert([
                'change_control_record_id' => $id, 'area_code' => $area,
                'assigned_to_user_id' => $assessorId, 'assigned_by_user_id' => $request->user()->id,
                'status' => 'assigned', 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record',
                'entity_id' => $id, 'action' => 'ccr.sme.assigned', 'old_values' => null,
                'new_values' => json_encode(['area_code' => $area, 'assigned_to_user_id' => $assessorId], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('ccr.sme.index', $id)->with('success', 'SME assignment saved. No assessment was submitted.');
    }
}
