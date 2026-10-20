<?php

namespace App\Services\Iar;

use Illuminate\Support\Facades\DB;

class AssignmentReadinessService
{
    public function summary(): array
    {
        $countries = DB::table('countries')
            ->join(
                'regions',
                'regions.id',
                '=',
                'countries.region_id'
            )
            ->select(
                'countries.id',
                'regions.assignment_strategy'
            )
            ->get();

        $otherIds = $countries
            ->where('assignment_strategy', 'country_assessor')
            ->pluck('id');

        $primary = DB::table('country_assessors')
            ->join(
                'users',
                'users.id',
                '=',
                'country_assessors.user_id'
            )
            ->whereIn('country_assessors.country_id', $otherIds)
            ->where('country_assessors.assignment_priority', 1)
            ->select(
                'country_assessors.country_id',
                'country_assessors.is_active as mapping_active',
                'users.is_active as user_active'
            )
            ->get()
            ->keyBy('country_id');

        $ready = 0;
        $missing = 0;
        $inactive = 0;

        foreach ($otherIds as $countryId) {
            $mapping = $primary->get($countryId);

            if (!$mapping) {
                $missing++;
                continue;
            }

            if (!$mapping->mapping_active || !$mapping->user_active) {
                $inactive++;
                continue;
            }

            $ready++;
        }

        return [
            'creator_countries' => $countries
                ->where('assignment_strategy', 'creator')
                ->count(),

            'other_countries' => $otherIds->count(),

            'ready_primary' => $ready,

            'missing_primary' => $missing,

            'inactive_primary' => $inactive,

            'unknown_registrations' => DB::table(
                'product_registrations'
            )
                ->where('registration_status', 'unknown')
                ->count(),
        ];
    }
}
