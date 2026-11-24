<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>My CCR actions | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6"><h1 class="text-2xl font-bold">My CCR actions</h1>
<p class="mt-2 text-sm text-slate-600">Proposed actions assigned to you. Read-only at this stage: no completion or implementation permission.</p>
@forelse($actions as $action)
<section class="mt-4 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">{{ $action->ccr_number }} · #{{ $action->id }} — {{ $action->title }}</p>
<p class="mt-2 whitespace-pre-wrap">{{ $action->description }}</p>
<p class="mt-2 text-sm">Due: {{ $action->due_date }} · Status: {{ $action->status }} · CCR: {{ $action->ccr_status }} · Dependency: {{ $action->depends_on_action_id ? '#'.$action->depends_on_action_id : 'None' }}</p>
</section>
@empty<section class="mt-4 rounded-xl bg-white p-5">No proposed actions assigned to your account.</section>@endforelse
<div class="mt-4">{{ $actions->links() }}</div>
</main></body></html>
