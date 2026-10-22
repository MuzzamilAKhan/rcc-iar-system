<?php

namespace App\Services\Iar;

use App\Models\ImpactAssessment;
use Illuminate\Support\Facades\DB;

final class IarSubmissionReadiness
{
    /** Read-only: no IAR status, assignments, or notifications are changed. */
    public function inspect(ImpactAssessment $iar): array
    {
        $productIds = DB::table('iar_products')
            ->where('impact_assessment_id', $iar->id)
            ->whereNotNull('product_id')
            ->pluck('product_id')->unique()->values();

        $rows = collect();
        $registrationIssues = collect();
        $unregisteredProductCount = 0;

        if ($productIds->isNotEmpty()) {
            $registrations = DB::table('product_registrations as r')
                ->join('products as p', 'p.id', '=', 'r.product_id')
                ->leftJoin('countries as c', 'c.id', '=', 'r.country_id')
                ->whereIn('r.product_id', $productIds)
                ->select('r.product_id', 'p.sap_code', 'r.country_id', 'r.registration_status', 'c.name as country_name')
                ->get();

            $registrationIssues = $registrations->filter(fn ($r) =>
                $r->country_id === null || $r->country_name === null ||
                in_array(strtolower(trim((string) $r->registration_status)), ['', 'unknown'], true)
            )->values();

            $registeredProductIds = $registrations->pluck('product_id')->unique();
            $unregisteredProductCount = $productIds->diff($registeredProductIds)->count();

            $countryIds = $registrations->pluck('country_id')->filter()->unique()->values();

            if ($countryIds->isNotEmpty()) {
                $countries = DB::table('countries as c')
                    ->join('regions as r', 'r.id', '=', 'c.region_id')
                    ->whereIn('c.id', $countryIds)
                    ->select('c.id', 'c.name', 'c.is_active as country_active', 'r.is_active as region_active', 'r.assignment_strategy')
                    ->orderBy('c.name')->get();

                $primary = DB::table('country_assessors as ca')
                    ->join('users as u', 'u.id', '=', 'ca.user_id')
                    ->whereIn('ca.country_id', $countryIds)
                    ->where('ca.assignment_priority', 1)
                    ->select('ca.country_id', 'ca.user_id', 'ca.is_active as mapping_active',
                        'u.name as assessor_name', 'u.is_active as user_active', 'u.provisioning_status')
                    ->get()->keyBy('country_id');

                $localRaIds = $primary->pluck('user_id')->filter()->values();
                $localRaUsers = DB::table('model_has_roles as m')
                    ->join('roles as r', 'r.id', '=', 'm.role_id')
                    ->where('m.model_type', 'App\\Models\\User')
                    ->where('r.name', 'local-ra')
                    ->whereIn('m.model_id', $localRaIds)
                    ->pluck('m.model_id')->map(fn ($id) => (int) $id)->all();

                $rows = $countries->map(function ($country) use ($primary, $localRaUsers, $iar) {
                    $blockers = [];
                    $assignee = null;
                    $assigneeId = null;
                    $strategy = $country->assignment_strategy;

                    if (!$country->country_active || !$country->region_active) {
                        $blockers[] = 'Country or region is inactive.';
                    }

                    if ($strategy === 'creator') {
                        $creator = $iar->creator;
                        if (!$creator || !$creator->is_active || !$creator->can('iar.create')) {
                            $blockers[] = 'IAR creator is not eligible for assignment.';
                        } else {
                            $assignee = $creator->name;
                            $assigneeId = $creator->id;
                        }
                    } elseif ($strategy === 'country_assessor') {
                        $mapping = $primary->get($country->id);
                        if (!$mapping) {
                            $blockers[] = 'Primary country assessor mapping is missing.';
                        } else {
                            $assignee = $mapping->assessor_name;
                            $assigneeId = (int) $mapping->user_id;
                            if (!$mapping->mapping_active) $blockers[] = 'Primary assessor mapping is inactive.';
                            if (!$mapping->user_active) $blockers[] = 'Primary assessor account is inactive.';
                            if (!in_array((int) $mapping->user_id, $localRaUsers, true)) $blockers[] = 'Primary assessor lacks Local RA role.';
                            if (!in_array($mapping->provisioning_status, ['approved', 'invited', 'active'], true)) {
                                $blockers[] = 'Primary assessor provisioning is not approved.';
                            }
                        }
                    } else {
                        $blockers[] = 'Unknown country assignment strategy.';
                    }

                    return [
                        'country_id' => (int) $country->id,
                        'country' => $country->name,
                        'strategy' => $strategy,
                        'assignee' => $assignee,
                        'assignee_id' => $assigneeId,
                        'ready' => count($blockers) === 0,
                        'blockers' => $blockers,
                    ];
                });
            }
        }

        $globalBlockers = [];
        if ($iar->status !== 'draft') $globalBlockers[] = 'Only draft IARs can be submitted.';
        if ($productIds->isEmpty()) $globalBlockers[] = 'No affected products are linked.';
        if ($rows->isEmpty()) $globalBlockers[] = 'No registered countries were found for the selected products.';
        if ($unregisteredProductCount > 0) $globalBlockers[] = 'One or more selected products have no country registration.';
        if ($registrationIssues->isNotEmpty()) $globalBlockers[] = 'One or more product registrations have unknown status or country.';
        if ($rows->contains(fn ($row) => !$row['ready'])) $globalBlockers[] = 'At least one country has an assignment blocker.';

        return [
            'iar_number' => $iar->iar_number,
            'product_count' => $productIds->count(),
            'country_count' => $rows->count(),
            'ready_count' => $rows->where('ready', true)->count(),
            'registration_issue_count' => $registrationIssues->count(),
            'can_submit' => $globalBlockers === [],
            'blockers' => $globalBlockers,
            'countries' => $rows->values()->all(),
        ];
    }
}
