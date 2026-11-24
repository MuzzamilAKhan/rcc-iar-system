<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Action evidence preparation | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-4xl p-6">
<a href="{{ route('ccr.actions.my') }}" style="color:var(--ra-accent)">&larr; My CCR actions</a>
<h1 class="mt-5 text-2xl font-bold">Action #{{ $action->id }} — Evidence preparation</h1>
<p class="mt-2">{{ $action->ccr_number }} · {{ $action->title }} · Action status: <strong>{{ strtoupper($action->status) }}</strong></p>
<p class="mt-2 text-sm">Evidence review is preliminary only. No action execution, completion, regulatory release, QA or CRB approval is enabled here.</p>
@if(session('success'))<div role="status" class="mt-4 rounded-lg p-4" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="mt-4 rounded-lg p-4" style="background:#fff1f2;color:#9f1239"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@if($evidence && $evidence->status==='returned')<div class="mt-4 rounded-lg p-4" style="background:#fff7e6;color:#704500"><strong>Returned for correction:</strong><p class="whitespace-pre-wrap">{{ $evidence->review_comment }}</p></div>@endif
<p class="mt-4">Evidence package: <strong>{{ strtoupper($evidence?->status ?? 'NOT STARTED') }}</strong></p>
@if($action->ccr_status==='draft' && $action->status==='proposed' && in_array($evidence?->status ?? 'draft',['draft','returned'],true))
<section class="mt-4 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)"><form method="POST" action="{{ route('ccr.action-evidence.save',$action->id) }}" class="space-y-4">@csrf
<label class="block font-semibold">Preparation/verification explanation (not completion claim)<textarea name="owner_explanation" required minlength="20" maxlength="10000" rows="5" class="mt-1 w-full rounded-lg border p-2" placeholder="What evidence should be reviewed and why?">{{ old('owner_explanation',$evidence?->owner_explanation) }}</textarea></label>
<label class="block font-semibold">Controlled evidence reference ID<input name="evidence_reference" required maxlength="150" value="{{ old('evidence_reference',$evidence?->evidence_reference) }}" class="mt-1 w-full rounded-lg border p-2"></label>
<label class="block font-semibold">Reference revision (optional)<input name="evidence_revision" maxlength="80" value="{{ old('evidence_revision',$evidence?->evidence_revision) }}" class="mt-1 w-full rounded-lg border p-2"></label>
<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0f8b8d;color:#fff">Save evidence preparation draft</button></form></section>
@if($evidence && filled($evidence->owner_explanation) && filled($evidence->evidence_reference))<form method="POST" action="{{ route('ccr.action-evidence.submit',$action->id) }}" class="mt-4">@csrf<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0e6b83;color:#fff">Submit evidence package for review</button></form>@endif
@else<section class="mt-4 rounded-xl border bg-white p-5"><p class="font-semibold">Editing locked</p><p class="mt-2 whitespace-pre-wrap">{{ $evidence?->owner_explanation }}</p><p class="mt-2">Evidence reference: {{ $evidence?->evidence_reference }} · {{ $evidence?->evidence_revision }}</p></section>@endif
</main></body></html>
