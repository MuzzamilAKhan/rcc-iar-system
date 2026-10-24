<?php

namespace App\Http\Controllers;

use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TimezonePreferenceController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.timezone', ['currentTimezone' => $request->user()->timezone ?: 'UTC']);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'max:100', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))],
        ]);
        $user = $request->user();
        DB::transaction(function () use ($user, $validated) {
            $user = $user->newQuery()->lockForUpdate()->findOrFail($user->id);
            $old = $user->timezone ?: 'UTC';
            $new = $validated['timezone'];
            if ($old === $new) return;
            $user->timezone = $new;
            $user->save();
            DB::table('audit_logs')->insert([
                'actor_user_id' => $user->id,
                'entity_type' => \App\Models\User::class,
                'entity_id' => $user->id,
                'action' => 'user.timezone.updated',
                'old_values' => json_encode(['timezone' => $old], JSON_THROW_ON_ERROR),
                'new_values' => json_encode(['timezone' => $new], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
        return redirect()->route('profile.timezone.edit')->with('success', 'Timezone preference saved. Audit records now display in your selected timezone.');
    }
}
