<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class IarReminderReadController
{
    public function __invoke(Request $request, int $notificationId): RedirectResponse
    {
        $notification = DB::table('notification_logs')
            ->where('id', $notificationId)
            ->where('recipient_user_id', $request->user()->id)
            ->whereIn('event_type', ['iar.reminder.creator', 'iar.reminder.assessor'])
            ->first(['id', 'read_at']);

        abort_if($notification === null, 404);

        if ($notification->read_at === null) {
            DB::table('notification_logs')
                ->where('id', $notificationId)
                ->where('recipient_user_id', $request->user()->id)
                ->whereNull('read_at')
                ->update(['read_at' => now('UTC'), 'updated_at' => now('UTC')]);
        }

        return redirect()->route('iar.reminders');
    }
}
