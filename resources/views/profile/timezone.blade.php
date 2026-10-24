<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Timezone | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100">
<main class="mx-auto max-w-2xl px-5 py-12">
    <a class="text-blue-700 hover:underline" href="{{ route('dashboard') }}">&larr; Dashboard</a>
    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">My Timezone</h1>
        <p class="mt-2 text-sm text-slate-600">Audit events are stored in UTC. This setting only changes how timestamps are displayed for your account. Select your office timezone, not necessarily your current travel location.</p>
        @if(session('success'))<p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>@endif
        <form method="POST" action="{{ route('profile.timezone.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-semibold" for="timezone">IANA timezone *</label>
                <input id="timezone" name="timezone" type="text" maxlength="100" required list="timezones" value="{{ old('timezone', $currentTimezone) }}" class="mt-2 w-full rounded-lg border p-3" autocomplete="off" spellcheck="false">
                <datalist id="timezones">
                    <option value="UTC"></option>
                    <option value="Europe/London"></option>
                    <option value="Asia/Karachi"></option>
                    <option value="America/Toronto"></option>
                    <option value="America/Vancouver"></option>
                    <option value="America/New_York"></option>
                    <option value="America/Chicago"></option>
                    <option value="America/Denver"></option>
                    <option value="America/Los_Angeles"></option>
                    <option value="Europe/Berlin"></option>
                    <option value="Asia/Dubai"></option>
                    <option value="Asia/Singapore"></option>
                </datalist>
                @error('timezone')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
                <button id="suggest-browser-timezone" type="button" class="mt-2 text-sm font-semibold text-blue-700 underline">Suggest my browser timezone</button>
                <p id="browser-timezone-hint" class="mt-2 text-xs text-slate-500" role="status"></p>
            </div>
            <p class="text-xs text-slate-600">Examples: Europe/London, Asia/Karachi, America/Toronto. Daylight saving is handled automatically. Use Save to confirm the preference.</p>
            <button class="rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white" type="submit">Save timezone</button>
        </form>
    </section>
</main>
<script>
    document.getElementById('suggest-browser-timezone')?.addEventListener('click', function () {
        const suggestion = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const hint = document.getElementById('browser-timezone-hint');
        if (suggestion) {
            document.getElementById('timezone').value = suggestion;
            hint.textContent = 'Suggested: ' + suggestion + '. Please review and click Save timezone.';
        } else {
            hint.textContent = 'Browser timezone could not be detected. Enter a timezone manually.';
        }
    });
</script>
</body>
</html>
