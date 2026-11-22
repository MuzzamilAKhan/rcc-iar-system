<?php

namespace App\Services\Iar;

use App\Mail\IarReminderMail;
use Carbon\CarbonImmutable;

/**
 * Read-only preparation of recipient-scoped mailables.
 * This class deliberately NEVER invokes Mail::send, queue, or database writes.
 */
final class ReminderMailDryRun
{
    public function __construct(
        private readonly ReminderPreview $preview,
        private readonly ReminderEmailPreview $formatter,
    ) {}

    /** @return array<int, array{iar_id:int,cycle:int,role:string,recipient_user_id:int,subject:string,mailable:IarReminderMail}> */
    public function prepare(?CarbonImmutable $asOf = null): array
    {
        $due = $this->preview->due($asOf);
        $messages = $this->formatter->messages($due);
        $eligible = [];
        foreach ($due as $row) {
            foreach ($row['recipients'] as $recipient) {
                $eligible[$row['iar_id'].':'.$row['cycle'].':'.$recipient['role'].':'.$recipient['user_id']] = true;
            }
        }
        $prepared = [];
        foreach ($messages as $message) {
            $key = $message['iar_id'].':'.$message['cycle'].':'.$message['role'].':'.$message['recipient_user_id'];
            if (!isset($eligible[$key])) continue;
            $prepared[] = [
                'iar_id' => (int) $message['iar_id'],
                'cycle' => (int) $message['cycle'],
                'role' => $message['role'],
                'recipient_user_id' => (int) $message['recipient_user_id'],
                'subject' => $message['subject'],
                'mailable' => new IarReminderMail($message['subject'], $message['body']),
            ];
        }
        return $prepared;
    }
}
