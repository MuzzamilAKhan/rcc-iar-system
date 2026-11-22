<?php

namespace App\Services\Iar;

/**
 * Read-only email content formatter for the current reminder preview.
 * Does not deliver, queue, persist, or access transport configuration.
 */
final class ReminderEmailPreview
{
    public function messages(array $due): array
    {
        $messages = [];
        foreach ($due as $row) {
            foreach ($row['recipients'] as $recipient) {
                if (!in_array($recipient['role'] ?? '', ['creator', 'assessor'], true)) {
                    continue;
                }
                $countries = array_values(array_unique(array_map('strval', $recipient['countries'] ?? [])));
                if (!$countries) continue;
                $role = $recipient['role'];
                $subject = 'RegAtlas: IAR ' . $row['iar_number'] . ' outstanding assessment reminder';
                $intro = $role === 'creator'
                    ? 'The following countries need assessment completion or assessor assignment. Please follow up with outstanding work.'
                    : 'The following country assessments assigned to you are still outstanding. Please review your assignments.';
                $body = "IAR: {$row['iar_number']}\nReminder cycle: {$row['cycle']}\n\n{$intro}\n\n";
                if ($role === 'creator') {
                    // Backward compatible with preview rows created before Phase 6C.3.
                    $assigned = $recipient['assigned_countries'] ?? $countries;
                    $unassigned = $recipient['unassigned_countries'] ?? [];
                    $body .= "Assigned assessments awaiting completion:\n";
                    foreach ($assigned as $country) $body .= "- {$country}\n";
                    $body .= "\nPending assessor assignment (no eligible primary Local RA):\n";
                    foreach ($unassigned as $country) $body .= "- {$country}\n";
                    if (!$unassigned) $body .= "None\n";
                } else {
                    // Assessors never receive unassigned country or other assessors' details.
                    foreach ($countries as $country) $body .= "- {$country}\n";
                }
                $body .= "\nSign in to RegAtlas to review the outstanding assessments.\n";
                $messages[] = [
                    'role' => $role,
                    'recipient_user_id' => (int) $recipient['user_id'],
                    'iar_id' => (int) $row['iar_id'],
                    'cycle' => (int) $row['cycle'],
                    'subject' => $subject,
                    'body' => $body,
                ];
            }
        }
        return $messages;
    }
}
