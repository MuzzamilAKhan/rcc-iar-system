<?php

namespace App\Services\Iar;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Opt-in in-app reminder staging. Never sends email or dispatches jobs. */
final class ReminderStager
{
    public function __construct(private readonly ReminderPreview $preview) {}

    public function stage(?CarbonImmutable $asOf = null): array
    {
        $results = ['created' => 0, 'existing' => 0, 'skipped' => 0];
        foreach ($this->preview->due($asOf) as $row) {
            foreach ($row['recipients'] as $recipient) {
                $role = $recipient['role'];
                if (!in_array($role, ['creator', 'assessor'], true)) {
                    $results['skipped']++;
                    continue;
                }
                $key = sprintf('iar-reminder-v1:%d:%d:%s:%d', $row['iar_id'], $row['cycle'], $role, $recipient['user_id']);
                $now = now('UTC');
                // Re-check cycle and outstanding status right before writing; preview is not a write authorization.
                $stillDue = collect($this->preview->due($asOf))->first(fn ($item) =>
                    $item['iar_id'] === $row['iar_id'] && $item['cycle'] === $row['cycle']);
                $stillRecipient = $stillDue && collect($stillDue['recipients'])->contains(fn ($r) =>
                    $r['role'] === $role && $r['user_id'] === $recipient['user_id'] && count($r['countries']) > 0);
                if (!$stillRecipient) {
                    $results['skipped']++;
                    continue;
                }
                // Unique idempotency_key prevents duplicate creation, even under concurrent invocations.
                $inserted = DB::table('notification_logs')->insertOrIgnore([
                    'recipient_user_id' => $recipient['user_id'],
                    'impact_assessment_id' => $row['iar_id'],
                    'iar_assignment_id' => null,
                    'event_type' => 'iar.reminder.' . $role,
                    'idempotency_key' => $key,
                    'status' => 'in_app',
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $results[$inserted ? 'created' : 'existing']++;
            }
        }
        return $results;
    }
}
