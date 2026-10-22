<?php

namespace App\Http\Controllers;

use App\Models\IarAssignment;
use App\Services\Iar\SaveAssessmentDraft;
use Illuminate\Http\Request;

final class AssessmentDraftController extends Controller
{
    private function authorized(Request $request, IarAssignment $assignment): void
    {
        abort_unless($request->user()?->is_active && $request->user()->can('assessment.edit.assigned') &&
            (int) $assignment->assigned_to_user_id === (int) $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()?->is_active && $request->user()->can('iar.view.assigned'), 403);
        $assignments = IarAssignment::query()
            ->where('assigned_to_user_id', $request->user()->id)
            ->with(['impactAssessment', 'country', 'response'])
            ->orderByDesc('assigned_at')->orderByDesc('id')->paginate(15);
        return view('assessment.index', compact('assignments'));
    }

    public function edit(Request $request, IarAssignment $assignment)
    {
        $this->authorized($request, $assignment);
        $assignment->load(['impactAssessment', 'country', 'response']);
        abort_unless($assignment->impactAssessment?->status === 'submitted', 403);
        return view('assessment.edit', compact('assignment'));
    }

    public function save(Request $request, IarAssignment $assignment, SaveAssessmentDraft $service)
    {
        $this->authorized($request, $assignment);
        $service->save($request->user(), $assignment, $request->all());
        return redirect()->route('assessment.edit', $assignment)->with('success', 'Assessment draft saved.');
    }
}
