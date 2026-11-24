<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Regulatory draft | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-4xl p-6">
<a href="{{ route('ccr.regulatory-drafts.my') }}" style="color:var(--ra-accent)">&larr; My CCR regulatory assessments</a>
<h1 class="mt-5 text-2xl font-bold">{{ $market->country_name }} ({{ $market->iso2 }}) — RA preliminary draft</h1>
<p class="mt-2 text-sm text-slate-600">{{ $market->ccr_number }} · {{ $market->title }}</p>
@if(session('success'))<div class="mt-4 rounded-lg p-4" role="status" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-lg border p-4" role="alert" style="background:#fff1f2;border-color:#fecdd3">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if($market->jurisdiction_hint === 'uk_jurisdiction_review')<section class="mt-5 rounded-xl border p-4" style="background:#fff7e6;border-color:#e3aa62">The generic UK registration does not distinguish GB from NI. Route must remain <strong>Unresolved</strong> until an authorized scope-resolution workflow is available.</section>@endif
<section class="mt-5 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<h2 class="font-bold">Source registration evidence</h2>
<p class="mt-1 text-sm">Source statuses below are preserved from Product Registry, including unknown/inactive statuses. Do not interpret them as market authorization.</p>
@foreach($sources as $source)<p class="mt-2 text-sm">SAP {{ $source->sap_code }} · Registration #{{ $source->product_registration_id }} · {{ $source->source_registration_status }}</p>@endforeach
</section>
@if($draft->status === 'returned' && $draft->review_comment)
<section class="mt-4 rounded-xl border p-4" style="background:#fff7e6;border-color:#e3aa62"><strong>Returned for corrections</strong><p class="mt-2 whitespace-pre-wrap">{{ $draft->review_comment }}</p></section>
@endif
@if(in_array($draft->status,['submitted','reviewed'],true))
<section class="mt-4 rounded-xl border p-4"><strong>Assessment {{ strtoupper($draft->status) }}:</strong> Editing locked pending the appropriate next step.</section>
@endif
<form method="POST" action="{{ route('ccr.regulatory-drafts.save', $market->id) }}" class="mt-5 space-y-4 rounded-xl bg-white p-5">@csrf
<label class="block font-semibold">Regulatory jurisdiction route (preliminary)
<select name="jurisdiction_route" required class="mt-1 w-full rounded-lg border p-2">
<option value="">Choose route</option>
@foreach(['eu_eea_review'=>'EU / EEA review','gb_review'=>'Great Britain review','ni_review'=>'Northern Ireland review','us_fda_review'=>'US / FDA review','local_review'=>'Other market / local RA review','unresolved'=>'Unresolved — jurisdiction confirmation required'] as $key=>$value)
<option value="{{ $key }}" @selected(old('jurisdiction_route', $draft->jurisdiction_route)===$key)>{{ $value }}</option>
@endforeach</select></label>
<label class="block font-semibold">Preliminary regulatory outcome
<select name="preliminary_outcome" required class="mt-1 w-full rounded-lg border p-2"><option value="">Choose preliminary outcome</option>
@foreach(['potential_notification'=>'Potential regulatory notification/submission','no_notification_indicated'=>'No notification indicated — requires review','additional_information_required'=>'Additional information required'] as $key=>$value)
<option value="{{ $key }}" @selected(old('preliminary_outcome', $draft->preliminary_outcome)===$key)>{{ $value }}</option>@endforeach
</select></label>
<label class="block font-semibold">Regulatory reasoning and assumptions
<textarea name="rationale" required minlength="20" maxlength="10000" rows="6" class="mt-1 w-full rounded-lg border p-2" placeholder="Cite the specific market, evidence and remaining questions">{{ old('rationale', $draft->rationale) }}</textarea></label>
<label class="block font-semibold">Evidence / source references (optional)
<textarea name="evidence_references" maxlength="5000" rows="3" class="mt-1 w-full rounded-lg border p-2" placeholder="Document IDs/revisions, guidance identifiers, not confidential document content">{{ old('evidence_references', $draft->evidence_references) }}</textarea></label>
@if($market->ccr_status === 'draft' && in_array($draft->status,['assigned','draft','returned'],true))<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0f8b8d!important;color:#fff!important">Save RA preliminary draft</button>
@else<p class="text-sm">Assessment/CCR editing is locked.</p>@endif
</form>
@if($market->ccr_status === 'draft' && in_array($draft->status,['draft','returned'],true))
<form method="POST" action="{{ route('ccr.regulatory-reviews.submit',$market->id) }}" class="mt-4">@csrf
<button class="rounded-lg px-5 py-3 font-semibold" style="background:#0e6b83!important;color:white!important">Submit RA assessment for review</button></form>
@endif
<p class="mt-4 text-sm text-slate-600">Regulatory assessment review does not grant market clearance, authority filing approval, QA/CRB approval, or implementation release.</p>
</main></body></html>
