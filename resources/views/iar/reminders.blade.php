<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My IAR Reminders | RegAtlas</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6" id="ra-reminders-main">
 <a href="{{ route('dashboard') }}" class="text-blue-700">&larr; Dashboard</a>
 <section class="mt-5 rounded-xl bg-white p-6 shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-4">
   <div><h1 class="text-2xl font-bold">My IAR reminders</h1>
   <p class="mt-2 text-sm text-slate-500">Your pending-assessment reminders. Automated delivery and emails are not yet enabled.</p></div>
   <span class="ra-reminder-count" aria-label="{{ $reminders->total() }} reminder records">{{ $reminders->total() }} records</span>
  </div>
  @forelse($reminders as $item)
   <article class="ra-reminder-entry mt-4 rounded-lg border p-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
     <strong>{{ $item->iar_number }}</strong>
     <time class="text-sm text-slate-500" datetime="{{ \Carbon\Carbon::parse($item->created_at, 'UTC')->toIso8601String() }}">{{ \Carbon\Carbon::parse($item->created_at, 'UTC')->setTimezone(auth()->user()->timezone ?: 'UTC')->format('Y-m-d H:i T') }}</time>
    </div>
    <p class="mt-2 text-sm">{{ $item->event_type === 'iar.reminder.creator' ? 'Creator reminder — outstanding country assessments require follow-up.' : 'Assessor reminder — your assigned country assessments require a response.' }}</p>
    <a href="{{ route('iar.show', $item->impact_assessment_id) }}" class="mt-2 inline-block text-sm font-semibold ra-reminder-link">Open IAR details</a>
   </article>
  @empty
   <div class="ra-reminder-empty mt-6 rounded-lg p-5"><strong>No reminders staged for your account.</strong><p class="mt-2 text-sm">Reminders appear here once an eligible submitted IAR has been staged in the local test workflow.</p></div>
  @endforelse
  <div class="mt-5">{{ $reminders->links() }}</div>
 </section>
</main>
</body>
</html>
