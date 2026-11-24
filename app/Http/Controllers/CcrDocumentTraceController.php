<?php

namespace App\Http\Controllers;

use App\Services\Ccr\ImpactAreaCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CCR5B: reference metadata only. No attachments, document approval or verified evidence claims. */
final class CcrDocumentTraceController
{
    private function authorized(Request $request, int $id): object
    {
        $ccr = DB::table('change_control_records')->where('id', $id)->first();
        abort_if(!$ccr, 404);
        abort_unless((int) $ccr->created_by === (int) $request->user()->id || $request->user()->hasRole('super-admin'), 403);
        return $ccr;
    }

    public function index(Request $request, int $id): View
    {
        $ccr = $this->authorized($request, $id);
        $documents = DB::table('ccr_document_impacts as d')
            ->leftJoin('users as u', 'u.id', '=', 'd.confirmed_by_user_id')
            ->where('d.change_control_record_id', $id)
            ->select('d.*', 'u.name as confirmer_name')->orderBy('d.id')->get();
        $evidence = DB::table('ccr_evidence_references')->where('change_control_record_id', $id)->orderBy('id')->get();
        $actions = DB::table('ccr_actions')->where('change_control_record_id', $id)->orderBy('id')->get(['id','title']);
        $areas = ImpactAreaCatalog::AREAS;
        return view('ccr.documents', compact('ccr', 'documents', 'evidence', 'actions', 'areas'));
    }

    public function addDocument(Request $request, int $id): RedirectResponse
    {
        $ccr = $this->authorized($request, $id);
        abort_unless($ccr->status === 'draft', 409);
        $data = Validator::make($request->all(), [
            'document_identifier' => ['required','string','min:2','max:120'],
            'document_revision' => ['required','string','max:80'],
            'document_title' => ['required','string','min:3','max:250'],
            'impact_rationale' => ['required','string','min:15','max:10000'],
            'impact_area_code' => ['nullable','string', Rule::in(array_keys(ImpactAreaCatalog::AREAS))],
            'related_action_id' => ['nullable','integer', Rule::exists('ccr_actions','id')],
        ])->validate();
        DB::transaction(function () use ($request, $id, $data) {
            $ccr = DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ccr && $ccr->status === 'draft', 409);
            $this->validateReferences($id, $data);
            $identifier = trim($data['document_identifier']);
            $revision = trim($data['document_revision']);
            abort_if(DB::table('ccr_document_impacts')->where('change_control_record_id', $id)
                ->where('document_identifier', $identifier)->where('document_revision', $revision)->exists(), 409,
                'This document identifier and revision are already recorded for the CCR.');
            $now = now('UTC');
            $newId = DB::table('ccr_document_impacts')->insertGetId([
                'change_control_record_id'=>$id, 'document_identifier'=>$identifier,
                'document_revision'=>$revision, 'document_title'=>trim($data['document_title']),
                'impact_rationale'=>trim($data['impact_rationale']), 'impact_area_code'=>$data['impact_area_code'] ?? null,
                'related_action_id'=>$data['related_action_id'] ?? null,
                'status'=>'potential', 'created_by_user_id'=>$request->user()->id,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $this->audit($request, $id, 'ccr.document.potential.recorded', null,
                ['document_impact_id'=>$newId,'identifier'=>$identifier,'revision'=>$revision,'status'=>'potential']);
        });
        return redirect()->route('ccr.documents.index', $id)->with('success','Potentially affected document recorded. Impact not yet confirmed.');
    }

    public function confirm(Request $request, int $id, int $documentId): RedirectResponse
    {
        $ccr = $this->authorized($request, $id);
        abort_unless($ccr->status === 'draft', 409);
        $data = Validator::make($request->all(), [
            'confirmation_rationale'=>['required','string','min:20','max:10000'],
        ])->validate();
        DB::transaction(function () use ($request, $id, $documentId, $data) {
            $ccr = DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ccr && $ccr->status === 'draft', 409);
            $document = DB::table('ccr_document_impacts')->where('id', $documentId)
                ->where('change_control_record_id', $id)->lockForUpdate()->first();
            abort_if(!$document, 404);
            abort_unless($document->status === 'potential', 409, 'Document impact already confirmed.');
            $now = now('UTC');
            DB::table('ccr_document_impacts')->where('id', $documentId)->update([
                'status'=>'confirmed','confirmation_rationale'=>trim($data['confirmation_rationale']),
                'confirmed_by_user_id'=>$request->user()->id,'confirmed_at'=>$now,'updated_at'=>$now,
            ]);
            $this->audit($request,$id,'ccr.document.impact.confirmed',
                ['document_impact_id'=>$documentId,'status'=>'potential'],
                ['document_impact_id'=>$documentId,'status'=>'confirmed']);
        });
        return redirect()->route('ccr.documents.index', $id)->with('success','Document impact confirmed. Controlled document revision approval remains separate.');
    }

    public function addEvidence(Request $request, int $id): RedirectResponse
    {
        $ccr = $this->authorized($request, $id);
        abort_unless($ccr->status === 'draft', 409);
        $data = Validator::make($request->all(), [
            'reference_identifier'=>['required','string','min:2','max:150'],
            'reference_revision'=>['nullable','string','max:80'],
            'evidence_description'=>['required','string','min:15','max:5000'],
            'document_impact_id'=>['nullable','integer', Rule::exists('ccr_document_impacts','id')],
            'related_action_id'=>['nullable','integer', Rule::exists('ccr_actions','id')],
        ])->validate();
        DB::transaction(function () use ($request,$id,$data) {
            $ccr = DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($ccr && $ccr->status === 'draft', 409);
            $this->validateReferences($id,$data);
            $now = now('UTC');
            $refId = DB::table('ccr_evidence_references')->insertGetId([
                'change_control_record_id'=>$id,'document_impact_id'=>$data['document_impact_id'] ?? null,
                'related_action_id'=>$data['related_action_id'] ?? null,
                'reference_identifier'=>trim($data['reference_identifier']),
                'reference_revision'=>trim($data['reference_revision'] ?? '') ?: null,
                'evidence_description'=>trim($data['evidence_description']),
                'evidence_status'=>'referenced','created_by_user_id'=>$request->user()->id,
                'created_at'=>$now,'updated_at'=>$now,
            ]);
            $this->audit($request,$id,'ccr.evidence.reference.recorded',null,
                ['evidence_reference_id'=>$refId,'identifier'=>trim($data['reference_identifier']),
                    'document_impact_id'=>$data['document_impact_id'] ?? null,
                    'related_action_id'=>$data['related_action_id'] ?? null,'status'=>'referenced']);
        });
        return redirect()->route('ccr.documents.index', $id)->with('success','Evidence metadata referenced. Content, authenticity and approval not verified.');
    }

    private function validateReferences(int $ccrId, array $data): void
    {
        if (!empty($data['impact_area_code'])) {
            abort_unless(DB::table('ccr_impact_decisions')->where('change_control_record_id', $ccrId)
                ->where('area_code', $data['impact_area_code'])->where('decision','selected')->exists(), 422,
                'Only selected impact areas may be linked.');
        }
        if (!empty($data['related_action_id'])) {
            abort_unless(DB::table('ccr_actions')->where('id',$data['related_action_id'])
                ->where('change_control_record_id',$ccrId)->exists(),422,'Action must belong to this CCR.');
        }
        if (!empty($data['document_impact_id'])) {
            abort_unless(DB::table('ccr_document_impacts')->where('id',$data['document_impact_id'])
                ->where('change_control_record_id',$ccrId)->exists(),422,'Document must belong to this CCR.');
        }
    }

    private function audit(Request $request,int $ccrId,string $action,?array $before,array $after): void
    {
        $now=now('UTC');
        DB::table('audit_logs')->insert([
            'actor_user_id'=>$request->user()->id,'entity_type'=>'change_control_record','entity_id'=>$ccrId,
            'action'=>$action,'old_values'=>$before ? json_encode($before,JSON_THROW_ON_ERROR) : null,
            'new_values'=>json_encode($after,JSON_THROW_ON_ERROR),'created_at'=>$now,'updated_at'=>$now,
        ]);
    }
}
