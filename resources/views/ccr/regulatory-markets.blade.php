<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mandatory regulatory market screening | {{ $record->ccr_number }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6">
<a href="{{ route('ccr.show', $record->id) }}" style="color:var(--ra-accent)">&larr; {{ $record->ccr_number }}</a>
<h1 class="mt-5 text-2xl font-bold">Mandatory regulatory market screening</h1>
<p class="mt-2 text-sm text-slate-600">CCR4A discovery snapshot only. Every discovered market requires regulatory screening regardless of whether the generic Regulatory impact area was selected. No regulatory conclusion, filing, clearance or market release is granted here.</p>
@if(session('success'))<div class="mt-4 rounded-lg p-4" role="status" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($impactDecisionCount !== 16)<div class="mt-4 rounded-lg border p-4" role="alert" style="background:#fff7e6;border-color:#e3aa62">All 16 cross-functional decisions must be recorded before initializing market screening. <a class="underline" href="{{ route('ccr.impacts.edit', $record->id) }}">Complete impact areas</a>.</div>@endif
@if($sources->isEmpty())<div class="mt-4 rounded-lg border p-4" role="alert" style="background:#fff1f2;border-color:#fecdd3">No linked-product country registration records were found. This is an unresolved coverage gap, <strong>not</strong> regulatory clearance. Manual market scope resolution will be required in a subsequent phase.</div>@endif
@if($missingSourceCount > 0 && $screenings->isNotEmpty())<div class="mt-4 rounded-lg border p-4" role="alert" style="background:#fff7e6;border-color:#e3aa62">{{ $missingSourceCount }} new/unsnapshotted registration source(s) detected. Refresh scope deliberately; existing screening decisions will not be erased.</div>@endif
<section class="mt-5 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<h2 class="font-bold">Product registry discovery preview</h2>
<p class="mt-2 text-sm">{{ $sources->count() }} registration record(s), {{ $sources->pluck('country_id')->unique()->count() }} distinct country record(s). All registration statuses are included; inactive/unknown statuses require human evaluation. A generic UK country record cannot distinguish Great Britain and Northern Ireland.</p>
@if($sources->isNotEmpty())<div class="mt-3 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">Market</th><th class="p-2">SAP</th><th class="p-2">Registration status</th><th class="p-2">Registry record ID</th></tr></thead><tbody>
@foreach($sources as $source)<tr class="border-t"><td class="p-2">{{ $source->country_name }} ({{ $source->iso2 }})</td><td class="p-2">{{ $source->sap_code }}</td><td class="p-2">{{ $source->registration_status }}</td><td class="p-2">{{ $source->registration_id }}</td></tr>@endforeach
</tbody></table></div>@endif
@if($record->status === 'draft' && $impactDecisionCount === 16 && $sources->isNotEmpty())
<form method="POST" action="{{ route('ccr.regulatory-markets.initialize', $record->id) }}" class="mt-4">@csrf
<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0f8b8d!important;color:white!important">{{ $screenings->isEmpty() ? 'Initialize mandatory market screening' : 'Record newly discovered registration sources' }}</button>
</form>@endif
</section>
<h2 class="mt-6 text-lg font-bold">Market screening queue — {{ $screenings->count() }} market(s)</h2>
@forelse($screenings as $screening)
<section class="mt-3 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">{{ $screening->country_name }} ({{ $screening->iso2 }})</p>
<p class="mt-1 text-sm">State: <strong>{{ str_replace('_', ' ', $screening->status) }}</strong> · Registered sources: {{ $sourceCounts[$screening->country_id] ?? 0 }}</p>
@if($screening->jurisdiction_hint === 'uk_jurisdiction_review')<p class="mt-2 text-sm" style="color:#92400e">UK jurisdiction unresolved: RA must distinguish GB versus Northern Ireland and identify the applicable route.</p>
@elseif($screening->jurisdiction_hint === 'us_review')<p class="mt-2 text-sm">US regulatory pathway assessment required from authorized RA.</p>
@else<p class="mt-2 text-sm">Applicable local/EEA/other jurisdiction pathway must be determined by qualified RA; no pathway inferred automatically.</p>@endif
</section>
@empty<section class="mt-3 rounded-xl bg-white p-5">No market screening records initialized yet. No market is deemed cleared.</section>@endforelse
<p class="mt-5 text-sm text-slate-600">CCR4B will add RA assignments and human-authored determinations. This view is a controlled discovery foundation, not a release decision.</p>
</main></body></html>
