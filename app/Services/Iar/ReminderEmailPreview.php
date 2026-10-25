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
                    ? 'The following countries still have outstanding country assessments. Please follow up with the assigned assessors.'
                    : 'The following country assessments assigned to you are still outstanding. Please review your assignments.';
                $body = "IAR: {$row['iar_number']}\nReminder cycle: {$row['cycle']}\n\n{$intro}\n\n";
                foreach ($countries as $country) $body .= "- {$country}\n";
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
