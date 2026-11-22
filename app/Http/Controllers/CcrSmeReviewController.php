<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CCR3C: assessment submission and independent admin review. Not CCR or regulatory approval. */
final class CcrSmeReviewController
{
    private function assignment(int $id): object
    {
        $row = DB::table('ccr_sme_assignments as a')
            ->join('change_control_records as c', 'c.id', '=', 'a.change_control_record_id')
            ->join('users as u', 'u.id', '=', 'a.assigned_to_user_id')
            ->where('a.id', $id)
            ->select('a.*', 'c.ccr_number', 'c.title', 'c.status as ccr_status', 'u.name as assessor_name')
            ->first();
        abort_if(!$row, 404);
        return $row;
    }

    public function submit(Request $request, int $assignmentId): RedirectResponse
    {
        $assignment = $this->assignment($assignmentId);
        abort_unless($request->user()->is_active && (int)$assignment->assigned_to_user_id === (int)$request->user()->id, 403);
        DB::transaction(function () use ($assignmentId, $request) {
            $row = DB::table('ccr_sme_assignments')->where('id', $assignmentId)->lockForUpdate()->first();
            abort_unless($row && (int)$row->assigned_to_user_id === (int)$request->user()->id, 403);
            abort_unless(in_array($row->status, ['draft', 'returned'], true), 409, 'Only saved drafts or returned assessments can be submitted.');
            $ccr = DB::table('change_control_records')->where('id', $row->change_control_record_id)->first();
            abort_unless($ccr && $ccr->status === 'draft', 409);
            abort_unless(DB::table('ccr_impact_decisions')->where('change_control_record_id', $ccr->id)
                ->where('area_code', $row->area_code)->where('decision', 'selected')->exists(), 409);
            $response = DB::table('ccr_sme_responses')->where('ccr_sme_assignment_id', $assignmentId)->first();
            abort_unless($response && in_array($response->answer, ['yes','no','uncertain'], true)
                && in_array($response->impact_conclusion, ['potential_impact','no_impact','further_assessment'], true)
                && mb_strlen(trim((string)$response->rationale)) >= 15, 422, 'Save a complete SME draft before submission.');
            $now = now('UTC');
            DB::table('ccr_sme_assignments')->where('id', $assignmentId)->update([
                'status' => 'submitted', 'submitted_at' => $now, 'reviewed_at' => null,
                'reviewed_by_user_id' => null, 'review_comment' => null, 'updated_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record',
                'entity_id' => $ccr->id, 'action' => 'ccr.sme.submitted',
                'old_values' => json_encode(['assignment_id'=>$assignmentId,'status'=>$row->status], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['assignment_id'=>$assignmentId,'status'=>'submitted'], JSON_THROW_ON_ERROR),
                'created_at'=>$now, 'updated_at'=>$now,
            ]);
        });
        return redirect()->route('ccr.sme-drafts.edit', $assignmentId)->with('success', 'SME assessment submitted for independent review.');
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $assignments = DB::table('ccr_sme_assignments as a')
            ->join('change_control_records as c', 'c.id','=','a.change_control_record_id')
            ->join('users as u','u.id','=','a.assigned_to_user_id')
            ->whereIn('a.status',['submitted','reviewed','returned'])
            ->select('a.id','a.area_code','a.status','c.ccr_number','c.title','u.name as assessor_name')
            ->orderByDesc('a.id')->paginate(15);
        $labels = \App\Services\Ccr\ImpactAreaCatalog::AREAS;
        return view('ccr.sme-review-queue', compact('assignments','labels'));
    }

    public function show(Request $request, int $assignmentId): View
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $assignment = $this->assignment($assignmentId);
        abort_if((int)$assignment->assigned_to_user_id === (int)$request->user()->id, 403, 'Self review is not allowed.');
        $response = DB::table('ccr_sme_responses')->where('ccr_sme_assignment_id', $assignmentId)->first();
        abort_if(!$response || !in_array($assignment->status,['submitted','reviewed','returned'],true), 404);
        $areaLabel = \App\Services\Ccr\ImpactAreaCatalog::AREAS[$assignment->area_code] ?? $assignment->area_code;
        return view('ccr.sme-review', compact('assignment','response','areaLabel'));
    }

    public function decide(Request $request, int $assignmentId): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);
        $input = Validator::make($request->all(), [
            'decision'=>['required',Rule::in(['reviewed','returned'])],
            'review_comment'=>['nullable','string','max:3000'],
        ])->after(function ($v) use ($request) {
            if ($request->input('decision')==='returned' && mb_strlen(trim((string)$request->input('review_comment'))) < 10) {
                $v->errors()->add('review_comment','Explain the requested corrections (at least 10 characters).');
            }
        })->validate();
        DB::transaction(function () use ($request,$assignmentId,$input) {
            $row = DB::table('ccr_sme_assignments')->where('id',$assignmentId)->lockForUpdate()->first();
            abort_if(!$row,404);
            abort_unless((int)$row->assigned_to_user_id !== (int)$request->user()->id,403,'Self review is not allowed.');
            abort_unless($row->status==='submitted',409,'Only submitted assessments may be reviewed.');
            $ccr = DB::table('change_control_records')->where('id',$row->change_control_record_id)->first();
            abort_unless($ccr && $ccr->status==='draft',409);
            $now=now('UTC');
            DB::table('ccr_sme_assignments')->where('id',$assignmentId)->update([
                'status'=>$input['decision'], 'reviewed_at'=>$now,
                'reviewed_by_user_id'=>$request->user()->id,
                'review_comment'=>trim((string)($input['review_comment']??'')) ?: null,
                'updated_at'=>$now,
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id'=>$request->user()->id,'entity_type'=>'change_control_record',
                'entity_id'=>$ccr->id,'action'=>'ccr.sme.review.decided',
                'old_values'=>json_encode(['assignment_id'=>$assignmentId,'status'=>'submitted'],JSON_THROW_ON_ERROR),
                'new_values'=>json_encode(['assignment_id'=>$assignmentId,'status'=>$input['decision'], 'comment'=>trim((string)($input['review_comment']??''))],JSON_THROW_ON_ERROR),
                'created_at'=>$now,'updated_at'=>$now,
            ]);
        });
        return redirect()->route('ccr.sme-reviews.show',$assignmentId)->with('success','Human review decision recorded. CCR approval not granted.');
    }
}
