<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>My IAR Reminders | RegAtlas</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<main class="mx-auto max-w-5xl p-6">
    <a href="{{ route('dashboard') }}" class="text-blue-700">&larr; Dashboard</a>
    <section class="mt-5 rounded-xl bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-bold">My IAR reminders</h1>
        <p class="mt-2 text-sm text-slate-500">In-app reminder records only. Email delivery and automatic scheduling are not enabled.</p>
        @forelse($reminders as $item)
            <article class="mt-4 rounded-lg border p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <strong>{{ $item->iar_number }}</strong>
                    <span class="text-sm text-slate-500">{{ \Carbon\Carbon::parse($item->created_at, 'UTC')->setTimezone(auth()->user()->timezone ?: 'UTC')->format('Y-m-d H:i T') }}</span>
                </div>
                <p class="mt-2 text-sm">{{ $item->event_type === 'iar.reminder.creator' ? 'Creator reminder — review outstanding country assessments.' : 'Assessor reminder — review your outstanding country assessments.' }}</p>
                <a href="{{ route('iar.show', $item->impact_assessment_id) }}" class="mt-2 inline-block text-sm font-semibold text-blue-700 hover:underline">Open IAR</a>
            </article>
        @empty
            <p class="mt-6 text-sm text-slate-500">No reminders staged for your account.</p>
        @endforelse
        <div class="mt-5">{{ $reminders->links() }}</div>
    </section>
</main>
</body>
</html>
