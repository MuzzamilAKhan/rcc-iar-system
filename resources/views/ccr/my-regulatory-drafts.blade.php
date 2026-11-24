<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My regulatory drafts | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6"><h1 class="text-2xl font-bold">My CCR regulatory assessments</h1>
<p class="mt-2 text-sm text-slate-600">Your assigned country screening drafts. These are not final regulatory determinations or market clearances.</p>
<div class="mt-5 space-y-3">@forelse($drafts as $draft)
<section class="rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">{{ $draft->ccr_number }} — {{ $draft->country_name }} ({{ $draft->iso2 }})</p>
<p class="mt-2 text-sm">{{ $draft->title }} · Draft state: {{ $draft->status }} · CCR: {{ $draft->ccr_status }}</p>
<a class="mt-3 inline-block rounded-lg px-4 py-2 font-semibold" style="background:#0f8b8d!important;color:#fff!important" href="{{ route('ccr.regulatory-drafts.edit', $draft->screening_id) }}">Open RA draft</a>
</section>@empty<section class="rounded-xl bg-white p-5">No markets assigned to your account.</section>@endforelse</div>
<div class="mt-4">{{ $drafts->links() }}</div></main></body></html>
