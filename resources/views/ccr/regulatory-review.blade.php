<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Regulatory review | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])<style>
/* Scoped CCR4C warning contrast in both RegAtlas themes; no changes to global theme. */
.ccr4c-acceptance-blockers,
.ccr4c-acceptance-blockers * { color: #704500 !important; }
.ccr4c-acceptance-blockers { background-color: #fff7e6 !important; border-color: #e3aa62 !important; }
</style></head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-4xl p-6"><a href="{{ route('ccr.regulatory-reviews.index') }}" style="color:var(--ra-accent)">&larr; Regulatory review queue</a>
<h1 class="mt-5 text-2xl font-bold">{{ $item->country_name }} ({{ $item->iso2 }}) — independent RA review</h1>
<p class="mt-2 text-sm">{{ $item->ccr_number }} · Assessor: {{ $item->assessor_name }} · {{ $item->status }}</p>
@if(session('success'))<div class="mt-4 rounded-lg p-4" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-lg p-4" role="alert" style="background:#fff1f2">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-5 rounded-xl bg-white p-5"><p><strong>Preliminary route:</strong> {{ str_replace('_',' ',$item->jurisdiction_route ?? 'Not entered') }}</p>
<p class="mt-2"><strong>Preliminary outcome:</strong> {{ str_replace('_',' ',$item->preliminary_outcome ?? 'Not entered') }}</p>
<h2 class="mt-4 font-bold">Rationale</h2><p class="whitespace-pre-wrap">{{ $item->rationale }}</p>
<h2 class="mt-4 font-bold">Evidence references</h2><p class="whitespace-pre-wrap">{{ $item->evidence_references ?: 'None supplied' }}</p>
<h2 class="mt-4 font-bold">Source registration snapshots</h2>
@foreach($sources as $source)<p class="text-sm">Registration #{{ $source->product_registration_id }}: {{ $source->source_registration_status }}</p>@endforeach</section>
@if(count($blockers))<section class="ccr4c-acceptance-blockers mt-4 rounded-xl border p-4" style="background:#fff7e6!important;border-color:#e3aa62!important;color:#704500!important"><strong>Unresolved acceptance blockers</strong><ul class="list-disc pl-5">@foreach($blockers as $blocker)<li>{{ $blocker }}</li>@endforeach</ul></section>@endif
@if($item->status === 'submitted' && $item->ccr_status === 'draft')<form class="mt-4 rounded-xl bg-white p-5" method="POST" action="{{ route('ccr.regulatory-reviews.decide',$item->screening_id) }}">@csrf
<label class="block font-bold">Review comment<textarea name="review_comment" maxlength="3000" rows="4" class="mt-2 w-full rounded-lg border p-2" placeholder="Required when returning for corrections">{{ old('review_comment') }}</textarea></label>
<div class="mt-4 flex flex-wrap gap-2"><button name="decision" value="reviewed" type="submit" @disabled(count($blockers)>0) class="rounded-lg px-4 py-2 font-semibold" style="background:#0f8b8d!important;color:white!important">Accept RA assessment</button>
<button name="decision" value="returned" type="submit" class="rounded-lg px-4 py-2 font-semibold" style="background:#a94429!important;color:white!important">Return for corrections</button></div></form>
@else<section class="mt-4 rounded-xl bg-white p-4">Review state: {{ $item->status }}. {{ $item->review_comment }}</section>@endif
<p class="mt-5 text-sm">Independent assessment review is not a regulatory filing decision, market authorization, implementation release, or CCR/QA approval.</p>
</main></body></html>
