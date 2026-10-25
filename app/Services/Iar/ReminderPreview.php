<?php

namespace App\Services\Iar;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Read-only reminder eligibility calculation; does not send or persist notifications. */
final class ReminderPreview
{
    /** @return array<int, array<string, mixed>> */
    public function due(?CarbonImmutable $asOf = null): array
    {
        $asOf ??= CarbonImmutable::now('UTC');
        $results = [];
        $iars = DB::table('impact_assessments')
            ->whereNotNull('submitted_at')
            ->where('status', 'submitted')
            ->where('submitted_at', '<=', $asOf->subDays(14)->toDateTimeString())
            ->select('id', 'iar_number', 'creator_id', 'submitted_at')
            ->orderBy('id')->get();

        foreach ($iars as $iar) {
            $submitted = CarbonImmutable::parse($iar->submitted_at, 'UTC');
            $days = $submitted->diffInDays($asOf, false);
            if ($days < 14) continue;
            $cycle = 1 + intdiv((int) floor($days) - 14, 7);
            $cycleAt = $submitted->addDays(14 + 7 * ($cycle - 1));

            $pending = DB::table('iar_assignments as a')
                ->join('countries as c', 'c.id', '=', 'a.country_id')
                ->leftJoin('assessment_responses as r', 'r.iar_assignment_id', '=', 'a.id')
                ->where('a.impact_assessment_id', $iar->id)
                ->whereNull('a.completed_at')
                ->where(function ($q) {
                    $q->whereNull('r.id')->orWhereNull('r.submitted_at');
                })
                ->select('a.id', 'a.assigned_to_user_id', 'c.name as country')
                ->orderBy('c.name')->get();
            if ($pending->isEmpty()) continue;
            $creator = DB::table('users')->where('id', $iar->creator_id)->where('is_active', 1)->first(['id', 'name', 'email']);
            $recipients = [];
            if ($creator) {
                $recipients[] = ['role' => 'creator', 'user_id' => (int) $creator->id,
                    'name' => $creator->name, 'email' => $creator->email,
                    'countries' => $pending->pluck('country')->values()->all()];
            }
            foreach ($pending->groupBy('assigned_to_user_id') as $userId => $assignments) {
                $user = DB::table('users')->where('id', $userId)->where('is_active', 1)->first(['id', 'name', 'email']);
                if (!$user) continue;
                $recipients[] = ['role' => 'assessor', 'user_id' => (int) $user->id,
                    'name' => $user->name, 'email' => $user->email,
                    'countries' => $assignments->pluck('country')->values()->all()];
            }
            if (!$recipients) continue;
            $results[] = [
                'iar_id' => (int) $iar->id, 'iar_number' => $iar->iar_number,
                'cycle' => $cycle, 'cycle_due_utc' => $cycleAt->toIso8601String(),
                'pending_country_count' => $pending->count(), 'recipients' => $recipients,
            ];
        }
        return $results;
    }
}
