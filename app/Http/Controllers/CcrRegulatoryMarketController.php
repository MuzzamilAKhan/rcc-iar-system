<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** CCR4A: mandatory market discovery only. No legal determination or market release. */
final class CcrRegulatoryMarketController
{
    private function record(Request $request, int $id): object
    {
        $record = DB::table('change_control_records')->where('id', $id)->first();
        abort_if($record === null, 404);
        abort_unless((int) $record->created_by === (int) $request->user()->id
            || $request->user()->hasRole('super-admin'), 403);
        return $record;
    }

    private function registrationSources(int $ccrId): \Illuminate\Support\Collection
    {
        // Deliberately include ALL registration statuses; an unknown/inactive record needs
        // explicit human scope assessment and must never be silently excluded.
        return DB::table('ccr_products as cp')
            ->join('product_registrations as pr', 'pr.product_id', '=', 'cp.product_id')
            ->join('countries as co', 'co.id', '=', 'pr.country_id')
            ->join('products as p', 'p.id', '=', 'pr.product_id')
            ->where('cp.change_control_record_id', $ccrId)
            ->select('pr.id as registration_id', 'pr.product_id', 'pr.country_id',
                'pr.registration_status', 'p.sap_code', 'co.name as country_name', 'co.iso2')
            ->orderBy('co.name')->orderBy('pr.id')->get();
    }

    private function hint(string $iso2): string
    {
        return match (strtoupper($iso2)) {
            'US' => 'us_review',
            // Existing country records cannot resolve GB vs NI from a generic UK record.
            'GB' => 'uk_jurisdiction_review',
            default => 'local_market_review',
        };
    }

    public function index(Request $request, int $id): View
    {
        $record = $this->record($request, $id);
        $sources = $this->registrationSources($id);
        $screenings = DB::table('ccr_regulatory_market_screenings as s')
            ->join('countries as co', 'co.id', '=', 's.country_id')
            ->where('s.change_control_record_id', $id)
            ->select('s.*', 'co.name as country_name', 'co.iso2')
            ->orderBy('co.name')->get();
        $sourceCounts = DB::table('ccr_regulatory_market_sources as src')
            ->join('ccr_regulatory_market_screenings as s', 's.id', '=', 'src.ccr_regulatory_market_screening_id')
            ->where('s.change_control_record_id', $id)
            ->selectRaw('s.country_id, COUNT(*) as total')
            ->groupBy('s.country_id')->pluck('total', 'country_id');
        $knownSourceIds = DB::table('ccr_regulatory_market_sources as src')
            ->join('ccr_regulatory_market_screenings as s', 's.id', '=', 'src.ccr_regulatory_market_screening_id')
            ->where('s.change_control_record_id', $id)->pluck('src.product_registration_id')->all();
        $missingSourceCount = $sources->whereNotIn('registration_id', $knownSourceIds)->count();
        $impactDecisionCount = DB::table('ccr_impact_decisions')
            ->where('change_control_record_id', $id)->count();
        $raDrafts = DB::table('ccr_regulatory_drafts as d')
            ->join('users as u', 'u.id', '=', 'd.assigned_to_user_id')
            ->join('ccr_regulatory_market_screenings as s', 's.id', '=', 'd.screening_id')
            ->where('s.change_control_record_id', $id)
            ->select('d.screening_id', 'd.assigned_to_user_id', 'd.status', 'u.name as assessor_name')
            ->get()->keyBy('screening_id');
        $raCandidates = \App\Models\User::query()->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['super-admin','global-ra','local-ra']))
            ->orderBy('name')->get(['id','name']);
        return view('ccr.regulatory-markets', compact('record', 'sources', 'screenings',
            'sourceCounts', 'missingSourceCount', 'impactDecisionCount', 'raDrafts', 'raCandidates'));
    }

    public function initialize(Request $request, int $id): RedirectResponse
    {
        $record = $this->record($request, $id);
        abort_unless($record->status === 'draft', 409, 'CCR is locked.');
        $added = DB::transaction(function () use ($request, $id) {
            $locked = DB::table('change_control_records')->where('id', $id)->lockForUpdate()->first();
            abort_unless($locked && $locked->status === 'draft', 409, 'CCR is locked.');
            $decisions = DB::table('ccr_impact_decisions')->where('change_control_record_id', $id)->count();
            abort_unless($decisions === 16, 409, 'Complete all 16 impact area decisions first.');
            $sources = $this->registrationSources($id);
            abort_if($sources->isEmpty(), 409, 'No product registration records found. Manual scope resolution required.');
            $now = now('UTC');
            $added = 0;
            foreach ($sources->groupBy('country_id') as $countryId => $countrySources) {
                $country = $countrySources->first();
                DB::table('ccr_regulatory_market_screenings')->insertOrIgnore([
                    'change_control_record_id' => $id, 'country_id' => $countryId,
                    'jurisdiction_hint' => $this->hint($country->iso2),
                    'status' => 'pending_regulatory_screening',
                    'created_by_user_id' => $request->user()->id,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $screeningId = DB::table('ccr_regulatory_market_screenings')
                    ->where('change_control_record_id', $id)->where('country_id', $countryId)->value('id');
                foreach ($countrySources as $source) {
                    $added += DB::table('ccr_regulatory_market_sources')->insertOrIgnore([
                        'ccr_regulatory_market_screening_id' => $screeningId,
                        'product_registration_id' => $source->registration_id,
                        'product_id' => $source->product_id,
                        'source_registration_status' => $source->registration_status,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
            if ($added > 0) {
                DB::table('audit_logs')->insert([
                    'actor_user_id' => $request->user()->id,
                    'entity_type' => 'change_control_record', 'entity_id' => $id,
                    'action' => 'ccr.regulatory.market_sources.recorded', 'old_values' => null,
                    'new_values' => json_encode(['added_registration_sources' => $added,
                        'country_count' => $sources->pluck('country_id')->unique()->count(),
                        'decision' => 'screening_required_for_all_discovered_markets'], JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return $added;
        });
        return redirect()->route('ccr.regulatory-markets.index', $id)->with('success',
            $added ? "$added registration source(s) recorded. All markets await qualified RA screening." :
                'No new registration sources. Existing screening records were preserved.');
    }
}
