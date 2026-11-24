<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>CCR document traceability | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6">
<a href="{{ route('ccr.show',$ccr->id) }}" style="color:var(--ra-accent)">&larr; {{ $ccr->ccr_number }}</a>
<h1 class="mt-5 text-2xl font-bold">Controlled document &amp; evidence traceability</h1>
<p class="mt-2 text-sm">Potentially affected documents and explicitly confirmed document impacts are separate. These entries are <strong>metadata references only</strong>: no controlled document approval, uploaded confidential content, evidence verification or implementation authorization.</p>
@if(session('success'))<div class="mt-4 rounded-lg p-4" role="status" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-lg p-4" role="alert" style="background:#fff1f2;color:#9f1239"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<h2 class="mt-6 text-lg font-bold">Document impact register — {{ $documents->count() }}</h2>
@forelse($documents as $document)
<section class="mt-3 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">#{{ $document->id }} · {{ $document->document_identifier }} · Rev {{ $document->document_revision }} — {{ $document->document_title }}</p>
<p class="mt-2 text-sm">Impact state: <strong>{{ strtoupper($document->status) }}</strong> · Area: {{ $areas[$document->impact_area_code] ?? 'General / cross-functional' }} · Action: {{ $document->related_action_id ? '#'.$document->related_action_id : 'None' }}</p>
<p class="mt-2 whitespace-pre-wrap text-sm">Potential impact basis: {{ $document->impact_rationale }}</p>
@if($document->status === 'confirmed')
<p class="mt-2 whitespace-pre-wrap text-sm" style="color:var(--ra-accent)"><strong>Explicit impact confirmation:</strong> {{ $document->confirmation_rationale }}</p>
<p class="mt-1 text-sm">Confirmed by {{ $document->confirmer_name }} · {{ $document->confirmed_at }}</p>
@elseif($ccr->status === 'draft')
<form method="POST" action="{{ route('ccr.documents.confirm',[$ccr->id,$document->id]) }}" class="mt-4 space-y-2">@csrf
<label class="block font-semibold">Confirm document impact with rationale (not document approval)
<textarea name="confirmation_rationale" rows="2" required minlength="20" maxlength="10000" class="mt-1 w-full rounded-lg border p-2" placeholder="Explain specific confirmed document impact and basis"></textarea></label>
<button type="submit" class="rounded-lg px-4 py-2 font-semibold" style="background:#0e6b83!important;color:#fff!important">Confirm impact</button></form>
@endif
</section>
@empty<section class="mt-3 rounded-xl bg-white p-5">No potentially affected documents recorded.</section>@endforelse
<h2 class="mt-6 text-lg font-bold">Evidence references — {{ $evidence->count() }}</h2>
@forelse($evidence as $ref)
<section class="mt-3 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">{{ $ref->reference_identifier }} @if($ref->reference_revision)· Rev {{ $ref->reference_revision }}@endif</p>
<p class="mt-1 text-sm whitespace-pre-wrap">{{ $ref->evidence_description }}</p>
<p class="mt-1 text-sm">Reference state: <strong>REFERENCED (not verified)</strong> · Document impact: {{ $ref->document_impact_id ? '#'.$ref->document_impact_id : 'None' }} · Proposed action: {{ $ref->related_action_id ? '#'.$ref->related_action_id : 'None' }}</p>
</section>
@empty<section class="mt-3 rounded-xl bg-white p-5">No evidence metadata references recorded.</section>@endforelse
@if($ccr->status === 'draft')
<section class="mt-6 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)"><h2 class="text-lg font-bold">Record potentially affected document</h2>
<form method="POST" action="{{ route('ccr.documents.add', $ccr->id) }}" class="mt-4 space-y-4">@csrf
<label class="block font-semibold">Controlled document identifier<input name="document_identifier" required maxlength="120" value="{{ old('document_identifier') }}" class="mt-1 w-full rounded-lg border p-2" placeholder="e.g. TEST-SPEC-001"></label>
<label class="block font-semibold">Current document revision<input name="document_revision" required maxlength="80" value="{{ old('document_revision') }}" class="mt-1 w-full rounded-lg border p-2" placeholder="e.g. Rev 2"></label>
<label class="block font-semibold">Document title<input name="document_title" required maxlength="250" value="{{ old('document_title') }}" class="mt-1 w-full rounded-lg border p-2"></label>
<label class="block font-semibold">Potential impact rationale<textarea name="impact_rationale" required minlength="15" maxlength="10000" rows="3" class="mt-1 w-full rounded-lg border p-2" placeholder="Why may this document be affected?">{{ old('impact_rationale') }}</textarea></label>
<label class="block font-semibold">Selected impact area (optional)<select name="impact_area_code" class="mt-1 w-full rounded-lg border p-2"><option value="">General / cross-functional</option>@foreach($areas as $key=>$label)<option value="{{ $key }}" @selected(old('impact_area_code')===$key)>{{ $label }}</option>@endforeach</select></label>
<label class="block font-semibold">Related proposed action (optional)<select name="related_action_id" class="mt-1 w-full rounded-lg border p-2"><option value="">No action link</option>@foreach($actions as $action)<option value="{{ $action->id }}" @selected((string)old('related_action_id')===(string)$action->id)>#{{ $action->id }} — {{ $action->title }}</option>@endforeach</select></label>
<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0f8b8d!important;color:#fff!important">Record potential impact</button></form></section>
<section class="mt-6 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)"><h2 class="text-lg font-bold">Add evidence metadata reference</h2>
<p class="mt-2 text-sm">Use document IDs, revisions or source identifiers only. Do not paste confidential evidence content or assume reference means verification.</p>
<form method="POST" action="{{ route('ccr.documents.evidence', $ccr->id) }}" class="mt-4 space-y-4">@csrf
<label class="block font-semibold">Reference identifier<input name="reference_identifier" required maxlength="150" class="mt-1 w-full rounded-lg border p-2" placeholder="e.g. TEST-SUPPLIER-QUAL-001"></label>
<label class="block font-semibold">Revision (optional)<input name="reference_revision" maxlength="80" class="mt-1 w-full rounded-lg border p-2"></label>
<label class="block font-semibold">Evidence description (metadata only)<textarea name="evidence_description" required minlength="15" maxlength="5000" rows="3" class="mt-1 w-full rounded-lg border p-2"></textarea></label>
<label class="block font-semibold">Related document impact (optional)<select name="document_impact_id" class="mt-1 w-full rounded-lg border p-2"><option value="">None</option>@foreach($documents as $document)<option value="{{ $document->id }}">#{{ $document->id }} — {{ $document->document_identifier }} ({{ $document->document_revision }})</option>@endforeach</select></label>
<label class="block font-semibold">Related proposed action (optional)<select name="related_action_id" class="mt-1 w-full rounded-lg border p-2"><option value="">None</option>@foreach($actions as $action)<option value="{{ $action->id }}">#{{ $action->id }} — {{ $action->title }}</option>@endforeach</select></label>
<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0f8b8d!important;color:#fff!important">Record evidence reference</button></form></section>
@else<p class="mt-5 text-sm">CCR is locked; existing traceability metadata remains visible but cannot be modified here.</p>@endif
</main></body></html>
