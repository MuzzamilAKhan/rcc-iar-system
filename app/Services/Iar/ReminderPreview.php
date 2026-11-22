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

            // Phase 6C.3: include unassigned country exceptions in the creator view.
            // Completed assignments and resolved exceptions are excluded.
            $snapshot = app(PendingReminderCountries::class)->forIar((int) $iar->id);
            $pending = $snapshot['assigned'];
            $unassigned = $snapshot['unassigned'];
            if ($pending->isEmpty() && $unassigned->isEmpty()) continue;
            $assignedNames = $pending->pluck('country')->unique()->values()->all();
            $unassignedNames = $unassigned->unique()->values()->all();
            $allNames = array_values(array_unique(array_merge($assignedNames, $unassignedNames)));
            sort($allNames, SORT_NATURAL | SORT_FLAG_CASE);
            $creator = DB::table('users')->where('id', $iar->creator_id)->where('is_active', 1)->first(['id', 'name', 'email']);
            $recipients = [];
            if ($creator) {
                $recipients[] = ['role' => 'creator', 'user_id' => (int) $creator->id,
                    'name' => $creator->name, 'email' => $creator->email,
                    'countries' => $allNames, 'assigned_countries' => $assignedNames,
                    'unassigned_countries' => $unassignedNames];
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
                'pending_country_count' => count($allNames),
                'assigned_pending_count' => count($assignedNames),
                'unassigned_country_count' => count($unassignedNames),
                'recipients' => $recipients,
            ];
        }
        return $results;
    }
}
