<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Market RA review | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6"><h1 class="text-2xl font-bold">Independent market RA review queue</h1>
<p class="mt-2 text-sm">Review of human-authored regulatory drafts only. Not a market clearance, authority filing, or CCR approval.</p>
@forelse($items as $item)<section class="mt-4 rounded-xl border bg-white p-5">
<p class="font-bold">{{ $item->ccr_number }} — {{ $item->country_name }} ({{ $item->iso2 }})</p>
<p class="mt-2 text-sm">Assessor: {{ $item->assessor_name }} · State: {{ $item->status }}</p>
<a href="{{ route('ccr.regulatory-reviews.show', $item->screening_id) }}" class="mt-3 inline-block rounded-lg px-4 py-2 font-semibold" style="background:#0f8b8d!important;color:white!important">View assessment</a>
</section>@empty<section class="mt-4 rounded-xl bg-white p-5">No submitted market assessments.</section>@endforelse
<div class="mt-4">{{ $items->links() }}</div></main></body></html>
