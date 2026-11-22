<?php

namespace App\Services\Iar;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Explicit, local-test-only outbox preparation. There is NO mail transport,
 * scheduler, event listener, queue worker, or automatically invoked route here.
 * Do not invoke against client data until the delivery policy is approved.
 */
final class ReminderEmailOutboxStager
{
    public function __construct(
        private readonly ReminderPreview $preview,
        private readonly ReminderEmailPreview $formatter,
    ) {}

    /** @return array{created:int,existing:int,skipped:int} */
    public function stage(?CarbonImmutable $asOf = null): array
    {
        $result = ['created' => 0, 'existing' => 0, 'skipped' => 0];
        // Reuse the established due/recipient scoping. Never derive recipients
        // directly from all IAR assignments or unrelated notification records.
        $due = $this->preview->due($asOf);
        foreach ($this->formatter->messages($due) as $message) {
            $role = $message['role'];
            $iarId = (int) $message['iar_id'];
            $cycle = (int) $message['cycle'];
            $userId = (int) $message['recipient_user_id'];
            if (!in_array($role, ['creator', 'assessor'], true)) {
                $result['skipped']++;
                continue;
            }

            // Eligibility can change between preview generation and insert.
            $current = collect($this->preview->due($asOf))->first(fn ($row) =>
                (int) $row['iar_id'] === $iarId && (int) $row['cycle'] === $cycle);
            $eligible = $current && collect($current['recipients'])->contains(fn ($r) =>
                (int) $r['user_id'] === $userId && $r['role'] === $role
                && count($r['countries'] ?? []) > 0);
            if (!$eligible) {
                $result['skipped']++;
                continue;
            }

            $key = sprintf('iar-reminder-email-v1:%d:%d:%s:%d', $iarId, $cycle, $role, $userId);
            $now = now('UTC');
            // DB unique constraint is the concurrency-safe deduplication gate.
            $inserted = DB::table('iar_email_outbox')->insertOrIgnore([
                'recipient_user_id' => $userId,
                'impact_assessment_id' => $iarId,
                'recipient_role' => $role,
                'reminder_cycle' => $cycle,
                'idempotency_key' => $key,
                'status' => 'pending',
                'subject' => $message['subject'],
                'body' => $message['body'],
                'attempt_count' => 0,
                'available_at' => $now,
                'sent_at' => null,
                'last_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $result[$inserted ? 'created' : 'existing']++;
        }
        return $result;
    }
}
