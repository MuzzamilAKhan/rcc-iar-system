<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


// Phase 6B.1: preview only; no email, notification or DB writes.
Artisan::command('iar:reminders-preview {--date= : UTC time such as 2026-10-15T09:00:00Z}', function (\App\Services\Iar\ReminderPreview $preview) {
    $input = $this->option('date');
    try {
        $asOf = $input ? \Carbon\CarbonImmutable::parse($input, 'UTC') : \Carbon\CarbonImmutable::now('UTC');
    } catch (\Throwable $e) {
        $this->error('Invalid UTC date: ' . $e->getMessage());
        return 1;
    }
    $rows = $preview->due($asOf);
    $this->info('Read-only reminder preview at ' . $asOf->toIso8601String());
    if (!$rows) $this->line('No reminders eligible.');
    foreach ($rows as $row) {
        $this->line($row['iar_number'] . ' | cycle ' . $row['cycle'] . ' | pending ' . $row['pending_country_count']);
        foreach ($row['recipients'] as $recipient) {
            $this->line('  ' . $recipient['role'] . ': ' . $recipient['name'] . ' (user #' . $recipient['user_id'] . ') | countries: ' . implode(', ', $recipient['countries']));
        }
    }
    $this->comment('PREVIEW ONLY. No notifications or email sent.');
    return 0;
})->purpose('Preview IAR reminders without creating or sending notifications');


// Phase 6B.2: explicitly opt-in local in-app staging, no email or scheduler.
Artisan::command('iar:reminders-stage {--date= : UTC evaluation time}', function (\App\Services\Iar\ReminderStager $stager) {
    $input = $this->option('date');
    try {
        $asOf = $input ? \Carbon\CarbonImmutable::parse($input, 'UTC') : \Carbon\CarbonImmutable::now('UTC');
    } catch (\Throwable $e) {
        $this->error('Invalid date: ' . $e->getMessage());
        return 1;
    }
    // Safety: simulated future dates may unintentionally stage notifications against real records.
    // This is intentionally an opt-in admin/developer CLI command. Do not schedule it.
    $counts = $stager->stage($asOf);
    $this->info('IN-APP ONLY: created ' . $counts['created'] . ', existing ' . $counts['existing'] . ', skipped ' . $counts['skipped']);
    $this->comment('No email sent. No scheduler configured.');
    return 0;
})->purpose('Explicitly stage deduplicated in-app IAR reminders; no email');
