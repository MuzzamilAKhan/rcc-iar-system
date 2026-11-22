<?php

namespace App\Services\Iar;

use Illuminate\Support\Facades\DB;

/**
 * Current, read-only country snapshot for a submitted IAR.
 * A deferred country must have an open assessor_unavailable exception and no
 * actual assignment. Never treat a resolved or assigned exception as pending.
 */
final class PendingReminderCountries
{
    public function forIar(int $iarId): array
    {
        $assigned = DB::table('iar_assignments as a')
            ->join('countries as c', 'c.id', '=', 'a.country_id')
            ->leftJoin('assessment_responses as r', 'r.iar_assignment_id', '=', 'a.id')
            ->where('a.impact_assessment_id', $iarId)
            ->whereNull('a.completed_at')
            ->where(function ($q) {
                $q->whereNull('r.id')->orWhereNull('r.submitted_at');
            })
            ->select('a.id', 'a.assigned_to_user_id', 'c.name as country')
            ->orderBy('c.name')
            ->get();

        $unassigned = DB::table('assignment_exceptions as e')
            ->join('countries as c', 'c.id', '=', 'e.country_id')
            ->where('e.impact_assessment_id', $iarId)
            ->where('e.exception_type', 'assessor_unavailable')
            ->where('e.resolution_status', 'open')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('iar_assignments as a')
                    ->whereColumn('a.impact_assessment_id', 'e.impact_assessment_id')
                    ->whereColumn('a.country_id', 'e.country_id');
            })
            ->orderBy('c.name')
            ->distinct()
            ->pluck('c.name');

        return ['assigned' => $assigned, 'unassigned' => $unassigned];
    }
}
