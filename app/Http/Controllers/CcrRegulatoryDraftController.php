<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** CCR4B: human RA assignment and PRIVATE DRAFT only; never regulatory approval/clearance. */
final class CcrRegulatoryDraftController
{
    private function market(int $screeningId): object
    {
        $market = DB::table('ccr_regulatory_market_screenings as s')
            ->join('change_control_records as c', 'c.id', '=', 's.change_control_record_id')
            ->join('countries as co', 'co.id', '=', 's.country_id')
            ->where('s.id', $screeningId)
            ->select('s.*', 'c.ccr_number', 'c.title', 'c.status as ccr_status', 'c.created_by as ccr_owner_id',
                'co.name as country_name', 'co.iso2')->first();
        abort_if($market === null, 404);
        return $market;
    }

    private function canManage(Request $request, object $market): void
    {
        abort_unless((int)$market->ccr_owner_id === (int)$request->user()->id || $request->user()->hasRole('super-admin'), 403);
    }

    public function assign(Request $request, int $screeningId): RedirectResponse
    {
        $market = $this->market($screeningId);
        $this->canManage($request, $market);
        abort_unless($market->ccr_status === 'draft', 409, 'CCR is locked.');
        $input = Validator::make($request->all(), [
            'assigned_to_user_id' => ['required', 'integer', Rule::exists('users', 'id')],
        ])->validate();
        $assessor = User::findOrFail($input['assigned_to_user_id']);
        abort_unless($assessor->is_active && $assessor->hasAnyRole(['super-admin','global-ra','local-ra']), 422, 'Choose an active RA user.');
        DB::transaction(function () use ($request, $market, $assessor) {
            $ccr = DB::table('change_control_records')->where('id', $market->change_control_record_id)->lockForUpdate()->first();
            abort_unless($ccr && $ccr->status === 'draft', 409);
            $screening = DB::table('ccr_regulatory_market_screenings')->where('id', $market->id)->first();
            abort_unless($screening && $screening->status === 'pending_regulatory_screening', 409);
            abort_unless(DB::table('ccr_regulatory_market_sources')->where('ccr_regulatory_market_screening_id', $market->id)->exists(), 409, 'No registered source.');
            $existing = DB::table('ccr_regulatory_drafts')->where('screening_id', $market->id)->first();
            abort_if($existing && (int)$existing->assigned_to_user_id !== (int)$assessor->id, 409, 'Already assigned. Reassignment requires future controlled workflow.');
            if ($existing) return;
            $now = now('UTC');
            DB::table('ccr_regulatory_drafts')->insert([
                'screening_id' => $market->id, 'assigned_to_user_id' => $assessor->id,
                'assigned_by_user_id' => $request->user()->id, 'status' => 'assigned',
                'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record',
                'entity_id' => $market->change_control_record_id, 'action' => 'ccr.regulatory.assigned',
                'old_values' => null,
                'new_values' => json_encode(['screening_id' => $market->id, 'country_id' => $market->country_id,
                    'assessor_id' => $assessor->id], JSON_THROW_ON_ERROR),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        });
        return redirect()->route('ccr.regulatory-markets.index', $market->change_control_record_id)
            ->with('success', 'Market RA assessor assigned. No regulatory decision has been made.');
    }

    public function myQueue(Request $request): View
    {
        $drafts = DB::table('ccr_regulatory_drafts as d')
            ->join('ccr_regulatory_market_screenings as s', 's.id', '=', 'd.screening_id')
            ->join('change_control_records as c', 'c.id', '=', 's.change_control_record_id')
            ->join('countries as co', 'co.id', '=', 's.country_id')
            ->where('d.assigned_to_user_id', $request->user()->id)
            ->select('d.id', 'd.screening_id', 'd.status', 'c.ccr_number', 'c.title', 'c.status as ccr_status',
                'co.name as country_name', 'co.iso2')->orderByDesc('d.id')->paginate(15);
        return view('ccr.my-regulatory-drafts', compact('drafts'));
    }

    private function assignee(Request $request, int $screeningId): array
    {
        $market = $this->market($screeningId);
        $draft = DB::table('ccr_regulatory_drafts')->where('screening_id', $screeningId)->first();
        abort_if(!$draft, 404);
        abort_unless((int)$draft->assigned_to_user_id === (int)$request->user()->id, 403);
        abort_unless($request->user()->is_active, 403);
        return [$market, $draft];
    }

    public function edit(Request $request, int $screeningId): View
    {
        [$market, $draft] = $this->assignee($request, $screeningId);
        $sources = DB::table('ccr_regulatory_market_sources as src')
            ->join('products as p', 'p.id', '=', 'src.product_id')
            ->where('src.ccr_regulatory_market_screening_id', $screeningId)
            ->select('src.product_registration_id', 'src.source_registration_status', 'p.sap_code')->orderBy('src.id')->get();
        return view('ccr.regulatory-draft', compact('market', 'draft', 'sources'));
    }

    public function save(Request $request, int $screeningId): RedirectResponse
    {
        [$market] = $this->assignee($request, $screeningId);
        abort_unless($market->ccr_status === 'draft', 409);
        $input = Validator::make($request->all(), [
            'jurisdiction_route' => ['required', Rule::in(['eu_eea_review', 'gb_review', 'ni_review', 'us_fda_review', 'local_review', 'unresolved'])],
            'preliminary_outcome' => ['required', Rule::in(['potential_notification', 'no_notification_indicated', 'additional_information_required'])],
            'rationale' => ['required', 'string', 'min:20', 'max:10000'],
            'evidence_references' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        DB::transaction(function () use ($request, $market, $screeningId, $input) {
            $ccr = DB::table('change_control_records')->where('id', $market->change_control_record_id)->lockForUpdate()->first();
            abort_unless($ccr && $ccr->status === 'draft', 409);
            $draft = DB::table('ccr_regulatory_drafts')->where('screening_id', $screeningId)->lockForUpdate()->first();
            abort_unless($draft && (int)$draft->assigned_to_user_id === (int)$request->user()->id, 403);
            abort_unless(in_array($draft->status, ['assigned','draft'], true), 409);
            $screening = DB::table('ccr_regulatory_market_screenings')->where('id', $screeningId)->first();
            abort_unless($screening && $screening->status === 'pending_regulatory_screening', 409);
            // A generic GB registry record never establishes GB-vs-NI scope. Do not permit a
            // conclusive jurisdiction choice from this country-level record alone.
            if ($market->jurisdiction_hint === 'uk_jurisdiction_review') {
                abort_unless($input['jurisdiction_route'] === 'unresolved', 422,
                    'UK registry needs an explicit GB/NI scope resolution in a future phase.');
            }
            // Country routing is only a human draft, but never accept clearly inconsistent
            // EU/US/GB/NI route labels for unrelated country records.
            $eea = ['AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','IS','LI','NO'];
            if ($market->iso2 === 'US') {
                abort_unless(in_array($input['jurisdiction_route'], ['us_fda_review','unresolved'], true), 422);
            } elseif ($market->iso2 !== 'GB') {
                abort_if(in_array($input['jurisdiction_route'], ['us_fda_review','gb_review','ni_review'], true), 422);
                abort_if($input['jurisdiction_route'] === 'eu_eea_review' && !in_array($market->iso2, $eea, true), 422);
            }
            $new = [
                'jurisdiction_route' => $input['jurisdiction_route'],
                'preliminary_outcome' => $input['preliminary_outcome'],
                'rationale' => trim($input['rationale']),
                'evidence_references' => trim($input['evidence_references'] ?? '') ?: null,
            ];
            if ($draft->status === 'draft' && $draft->jurisdiction_route === $new['jurisdiction_route']
                && $draft->preliminary_outcome === $new['preliminary_outcome']
                && $draft->rationale === $new['rationale']
                && $draft->evidence_references === $new['evidence_references']) return;
            $now = now('UTC');
            DB::table('ccr_regulatory_drafts')->where('id', $draft->id)->update($new + ['status'=>'draft', 'updated_at'=>$now]);
            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id, 'entity_type' => 'change_control_record',
                'entity_id' => $market->change_control_record_id, 'action' => 'ccr.regulatory.draft.saved',
                'old_values' => json_encode(['screening_id'=>$screeningId,'status'=>$draft->status,
                    'route'=>$draft->jurisdiction_route,'outcome'=>$draft->preliminary_outcome], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['screening_id'=>$screeningId,'status'=>'draft',
                    'route'=>$new['jurisdiction_route'],'outcome'=>$new['preliminary_outcome']], JSON_THROW_ON_ERROR),
                'created_at'=>$now, 'updated_at'=>$now,
            ]);
        });
        return redirect()->route('ccr.regulatory-drafts.edit', $screeningId)
            ->with('success', 'RA preliminary draft saved. Not submitted, reviewed or cleared.');
    }
}
