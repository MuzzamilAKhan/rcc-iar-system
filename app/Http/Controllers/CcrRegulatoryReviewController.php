<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CCR4C: review of human-authored market assessments; NEVER a regulatory/market release authorization. */
final class CcrRegulatoryReviewController
{
    private function item(int $screeningId): object
    {
        $row = DB::table('ccr_regulatory_drafts as d')
            ->join('ccr_regulatory_market_screenings as s', 's.id', '=', 'd.screening_id')
            ->join('change_control_records as c', 'c.id', '=', 's.change_control_record_id')
            ->join('countries as co', 'co.id', '=', 's.country_id')
            ->join('users as u', 'u.id', '=', 'd.assigned_to_user_id')
            ->where('d.screening_id', $screeningId)
            ->select('d.*', 's.change_control_record_id', 's.jurisdiction_hint', 's.status as market_status',
                'c.ccr_number', 'c.title', 'c.status as ccr_status', 'co.name as country_name',
                'co.iso2', 'u.name as assessor_name')->first();
        abort_if(!$row, 404);
        return $row;
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->is_active && $request->user()->hasRole('super-admin'), 403);
    }

    public function submit(Request $request, int $screeningId): RedirectResponse
    {
        $item = $this->item($screeningId);
        abort_unless($request->user()->is_active && (int)$item->assigned_to_user_id === (int)$request->user()->id, 403);
        DB::transaction(function () use ($request, $screeningId) {
            $draft = DB::table('ccr_regulatory_drafts')->where('screening_id', $screeningId)->lockForUpdate()->first();
            abort_unless($draft && (int)$draft->assigned_to_user_id === (int)$request->user()->id, 403);
            abort_unless(in_array($draft->status, ['draft','returned'], true), 409, 'Save a draft before submission.');
            $screen = DB::table('ccr_regulatory_market_screenings')->where('id', $screeningId)->first();
            $ccr = $screen ? DB::table('change_control_records')->where('id', $screen->change_control_record_id)->first() : null;
            abort_unless($ccr && $ccr->status === 'draft' && $screen->status === 'pending_regulatory_screening', 409);
            abort_unless($draft->jurisdiction_route && $draft->preliminary_outcome
                && mb_strlen(trim((string)$draft->rationale)) >= 20, 422, 'Complete the regulatory draft first.');
            abort_unless(DB::table('ccr_regulatory_market_sources')->where('ccr_regulatory_market_screening_id', $screeningId)->exists(), 409);
            $now = now('UTC');
            DB::table('ccr_regulatory_drafts')->where('id', $draft->id)->update([
                'status'=>'submitted','submitted_at'=>$now,'reviewed_at'=>null,'reviewed_by_user_id'=>null,
                'review_comment'=>null,'updated_at'=>$now,
            ]);
            $this->audit($request, $ccr->id, 'ccr.regulatory.submitted',
                ['screening_id'=>$screeningId,'status'=>$draft->status],
                ['screening_id'=>$screeningId,'status'=>'submitted']);
        });
        return redirect()->route('ccr.regulatory-drafts.edit', $screeningId)
            ->with('success', 'Market RA assessment submitted for independent review. No clearance granted.');
    }

    public function index(Request $request): View
    {
        $this->admin($request);
        $items = DB::table('ccr_regulatory_drafts as d')
            ->join('ccr_regulatory_market_screenings as s','s.id','=','d.screening_id')
            ->join('change_control_records as c','c.id','=','s.change_control_record_id')
            ->join('countries as co','co.id','=','s.country_id')
            ->join('users as u','u.id','=','d.assigned_to_user_id')
            ->whereIn('d.status',['submitted','reviewed','returned'])
            ->select('d.screening_id','d.status','c.ccr_number','co.name as country_name','co.iso2','u.name as assessor_name')
            ->orderByRaw("CASE WHEN d.status = 'submitted' THEN 0 ELSE 1 END")
            ->orderByDesc('d.updated_at')->paginate(15);
        return view('ccr.regulatory-review-queue', compact('items'));
    }

    public function show(Request $request, int $screeningId): View
    {
        $this->admin($request);
        $item = $this->item($screeningId);
        abort_unless(in_array($item->status, ['submitted','reviewed','returned'], true), 404);
        abort_unless((int)$item->assigned_to_user_id !== (int)$request->user()->id, 403, 'Self-review prohibited.');
        $sources = DB::table('ccr_regulatory_market_sources')
            ->where('ccr_regulatory_market_screening_id', $screeningId)->get();
        $blockers = $this->blockers($item, $sources);
        return view('ccr.regulatory-review', compact('item','sources','blockers'));
    }

    private function blockers(object $item, $sources): array
    {
        $issues=[];
        if ($sources->isEmpty()) $issues[]='No registration source exists.';
        if ($sources->contains(fn ($s) => $s->source_registration_status !== 'active'))
            $issues[]='At least one source registration is not verified active.';
        if ($item->jurisdiction_hint === 'uk_jurisdiction_review' || $item->jurisdiction_route === 'unresolved')
            $issues[]='Jurisdiction/scope unresolved.';
        if ($item->preliminary_outcome === 'additional_information_required')
            $issues[]='Regulatory assessment needs additional information.';
        return $issues;
    }

    public function decide(Request $request, int $screeningId): RedirectResponse
    {
        $this->admin($request);
        $input = Validator::make($request->all(), [
            'decision'=>['required',Rule::in(['reviewed','returned'])],
            'review_comment'=>['nullable','string','max:3000'],
        ])->after(function ($v) use ($request) {
            if ($request->input('decision') === 'returned' && mb_strlen(trim((string)$request->input('review_comment'))) < 10)
                $v->errors()->add('review_comment','Explain requested corrections (at least 10 characters).');
        })->validate();
        DB::transaction(function () use ($request,$screeningId,$input) {
            $draft = DB::table('ccr_regulatory_drafts')->where('screening_id',$screeningId)->lockForUpdate()->first();
            abort_if(!$draft,404);
            abort_unless((int)$draft->assigned_to_user_id !== (int)$request->user()->id,403);
            abort_unless($draft->status === 'submitted',409,'Only submitted drafts can be reviewed.');
            $screen=DB::table('ccr_regulatory_market_screenings')->where('id',$screeningId)->first();
            $ccr=$screen ? DB::table('change_control_records')->where('id',$screen->change_control_record_id)->first():null;
            abort_unless($ccr && $ccr->status === 'draft' && $screen->status === 'pending_regulatory_screening',409);
            if ($input['decision'] === 'reviewed') {
                $sourceCount=DB::table('ccr_regulatory_market_sources')->where('ccr_regulatory_market_screening_id',$screeningId)->count();
                abort_unless($sourceCount > 0,409,'Missing market registration sources.');
                // Do not accept an unresolved jurisdiction or additional-info-needed draft.
                abort_unless($screen->jurisdiction_hint !== 'uk_jurisdiction_review'
                    && $draft->jurisdiction_route !== 'unresolved'
                    && $draft->preliminary_outcome !== 'additional_information_required',409,
                    'Unresolved regulatory determination cannot be accepted. Return for corrections.');
                abort_unless(!DB::table('ccr_regulatory_market_sources')->where('ccr_regulatory_market_screening_id',$screeningId)
                    ->where('source_registration_status','!=','active')->exists(),409,
                    'Unknown/inactive registrations require scope resolution before acceptance.');
            }
            $now=now('UTC');
            DB::table('ccr_regulatory_drafts')->where('id',$draft->id)->update([
                'status'=>$input['decision'],'reviewed_at'=>$now,'reviewed_by_user_id'=>$request->user()->id,
                'review_comment'=>trim((string)($input['review_comment']??'')) ?: null,'updated_at'=>$now,
            ]);
            $this->audit($request,$ccr->id,'ccr.regulatory.review.decided',
                ['screening_id'=>$screeningId,'status'=>'submitted'],
                ['screening_id'=>$screeningId,'status'=>$input['decision'],
                    'comment'=>trim((string)($input['review_comment']??''))]);
        });
        return redirect()->route('ccr.regulatory-reviews.show',$screeningId)
            ->with('success','Independent RA review recorded. No market release, filing, or CCR approval granted.');
    }

    public function readiness(Request $request, int $ccrId): View
    {
        $ccr=DB::table('change_control_records')->where('id',$ccrId)->first();
        abort_if(!$ccr,404);
        abort_unless((int)$ccr->created_by === (int)$request->user()->id || $request->user()->hasRole('super-admin'),403);
        $markets=DB::table('ccr_regulatory_market_screenings as s')
            ->join('countries as co','co.id','=','s.country_id')
            ->leftJoin('ccr_regulatory_drafts as d','d.screening_id','=','s.id')
            ->where('s.change_control_record_id',$ccrId)
            ->select('s.id','co.name','co.iso2','s.jurisdiction_hint','s.status as market_status',
                'd.status as draft_status','d.jurisdiction_route','d.preliminary_outcome')
            ->orderBy('co.name')->get();
        $sourceCoverage=DB::table('ccr_products as cp')
            ->join('product_registrations as pr','pr.product_id','=','cp.product_id')
            ->where('cp.change_control_record_id',$ccrId)->count();
        $snapshotted=DB::table('ccr_regulatory_market_sources as ms')
            ->join('ccr_regulatory_market_screenings as s','s.id','=','ms.ccr_regulatory_market_screening_id')
            ->where('s.change_control_record_id',$ccrId)->count();
        $rows=[];
        foreach($markets as $market){
            $source=DB::table('ccr_regulatory_market_sources')
                ->where('ccr_regulatory_market_screening_id',$market->id)->get();
            $obstacles=[];
            if($market->draft_status !== 'reviewed') $obstacles[]='Independent RA review incomplete.';
            if($market->jurisdiction_hint === 'uk_jurisdiction_review'||$market->jurisdiction_route === 'unresolved') $obstacles[]='Jurisdiction unresolved.';
            if($market->preliminary_outcome === 'additional_information_required') $obstacles[]='Further evidence required.';
            if($source->isEmpty()||$source->contains(fn($s)=>$s->source_registration_status !== 'active')) $obstacles[]='Registration status/source requires verification.';
            $rows[]=['market'=>$market,'blockers'=>$obstacles];
        }
        $coverageGap=$markets->isEmpty()||$sourceCoverage===0||$snapshotted<$sourceCoverage;
        return view('ccr.regulatory-readiness',compact('ccr','rows','coverageGap','sourceCoverage','snapshotted'));
    }

    private function audit(Request $request,int $ccrId,string $action,array $old,array $new): void
    {
        $now=now('UTC');
        DB::table('audit_logs')->insert([
            'actor_user_id'=>$request->user()->id,'entity_type'=>'change_control_record','entity_id'=>$ccrId,
            'action'=>$action,'old_values'=>json_encode($old,JSON_THROW_ON_ERROR),
            'new_values'=>json_encode($new,JSON_THROW_ON_ERROR),'created_at'=>$now,'updated_at'=>$now,
        ]);
    }
}
