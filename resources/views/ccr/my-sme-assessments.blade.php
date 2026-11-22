<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My CCR Assessments | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6"><h1 class="text-2xl font-bold">My CCR SME assessments</h1>
<p class="mt-2 text-sm text-slate-600">Only assignments addressed to your account appear here. Assessments stay editable until formally submitted; human review is independent.</p>
<div class="mt-5 space-y-3">@forelse($assignments as $assignment)
<section class="rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">{{ $assignment->ccr_number }} — {{ $labels[$assignment->area_code] ?? $assignment->area_code }}</p>
<p class="mt-1 text-sm">{{ $assignment->title }} · CCR: {{ $assignment->ccr_status }} · Assessment: {{ $assignment->assignment_status }}</p>
<a class="mt-3 inline-block rounded-lg px-4 py-2 font-semibold" style="background:#0f8b8d!important;color:#fff!important" href="{{ route('ccr.sme-drafts.edit', $assignment->id) }}">Open SME workspace</a>
</section>@empty<section class="rounded-xl bg-white p-5">No CCR SME assignments currently assigned to you.</section>@endforelse</div>
<div class="mt-4">{{ $assignments->links() }}</div></main></body></html>
