<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My Timezone | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100">
@include('partials.regatlas-navigation')

<main class="mx-auto max-w-2xl px-5 py-12">
    <a class="text-blue-700 hover:underline" href="{{ route('dashboard') }}">&larr; Dashboard</a>
    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">My Timezone</h1>
        <p class="mt-2 text-sm text-slate-600">Audit events are stored in UTC. Select the timezone for your account; daylight saving changes are applied automatically.</p>
        @if (session('success'))
            <p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p>
        @endif
        <form method="POST" action="{{ route('profile.timezone.update') }}" class="mt-6 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label for="timezone-search" class="block text-sm font-semibold">Search and select timezone *</label>
                <input id="timezone-search" name="timezone" type="text"
                    list="timezone-options" autocomplete="off" required
                    value="{{ old('timezone', $currentTimezone) }}"
                    placeholder="Type London, Karachi, Toronto, New_York..."
                    class="mt-2 w-full rounded-lg border p-3"
                    aria-describedby="timezone-help">
                <datalist id="timezone-options">
                    @foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $zone)
                        <option value="{{ $zone }}"></option>
                    @endforeach
                    <option value="UTC"></option>
                </datalist>
                <p id="timezone-help" class="mt-2 text-xs text-slate-600">Click the field to browse, or type part of a city/region to filter the timezone list. Select an exact timezone identifier.</p>
                <button id="suggest-browser-timezone" type="button" class="mt-3 text-sm font-semibold text-blue-700 underline">Suggest my browser timezone</button>
                <p id="browser-timezone-note" class="mt-1 text-xs text-slate-600" role="status"></p>
                @error('timezone')
                    <p class="mt-2 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
            <button class="rounded-lg bg-blue-700 px-5 py-3 font-semibold text-white" type="submit">Save timezone</button>
        </form>
    </section>
</main>
<script>
(() => {
    const field = document.getElementById('timezone-search');
    const button = document.getElementById('suggest-browser-timezone');
    const note = document.getElementById('browser-timezone-note');
    button.addEventListener('click', () => {
        try {
            const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (!tz) { note.textContent = 'Browser did not provide a timezone.'; return; }
            field.value = tz;
            note.textContent = `Suggested ${tz}. Click Save timezone to confirm.`;
        } catch (_) {
            note.textContent = 'Could not determine browser timezone. Please select from the list.';
        }
    });
})();
</script>
</body>
</html>
