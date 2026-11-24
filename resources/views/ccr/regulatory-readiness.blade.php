<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Regulatory readiness | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6"><a href="{{ route('ccr.regulatory-markets.index',$ccr->id) }}" style="color:var(--ra-accent)">&larr; Regulatory market screening</a>
<h1 class="mt-5 text-2xl font-bold">{{ $ccr->ccr_number }} — regulatory readiness preview</h1>
<p class="mt-2 text-sm">Read-only blockers preview. This is not implementation authorization or regulatory clearance.</p>
@if($coverageGap)<section class="mt-4 rounded-xl border p-4" style="background:#fff1f2;border-color:#fecdd3"><strong>Blocked:</strong> Market coverage incomplete, no registration sources, or new unsnapshotted sources.</section>@endif
@forelse($rows as $entry)<section class="mt-4 rounded-xl bg-white p-5"><h2 class="font-bold">{{ $entry['market']->name }} ({{ $entry['market']->iso2 }})</h2>
<p class="mt-2 text-sm">Assessment: {{ $entry['market']->draft_status ?: 'unassigned' }} · Screening: {{ $entry['market']->market_status }}</p>
@if(count($entry['blockers']))<ul class="mt-2 list-disc pl-5">@foreach($entry['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach</ul>
@else<p class="mt-2">No *current preview* blockers detected; additional formal approval and release controls are still required.</p>@endif
</section>@empty<section class="mt-4 rounded-xl bg-white p-5">No markets initialized. Regulatory readiness is blocked.</section>@endforelse
</main></body></html>
