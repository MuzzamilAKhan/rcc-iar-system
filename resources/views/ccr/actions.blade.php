<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CCR proposed action register | CMSoftware</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6">
<a href="{{ route('ccr.show', $ccr->id) }}" style="color:var(--ra-accent)">&larr; {{ $ccr->ccr_number }}</a>
<h1 class="mt-5 text-2xl font-bold">Proposed action register</h1>
<p class="mt-2 text-sm text-slate-600">Actions are proposals only. This register does not authorize implementation, market release, QA/CRB approval or completion. Assigned owners can read their tasks in My CCR actions.</p>
@if(session('success'))<div class="mt-4 rounded-lg p-4" role="status" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-lg p-4" role="alert" style="background:#fff1f2;color:#9f1239"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<h2 class="mt-6 text-lg font-bold">Recorded actions — {{ $actions->count() }}</h2>
@forelse($actions as $action)
<section class="mt-3 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<p class="font-bold">#{{ $action->id }} — {{ $action->title }}</p><p class="mt-2 whitespace-pre-wrap">{{ $action->description }}</p>
<p class="mt-2 text-sm">Owner: <strong>{{ $action->owner_name }}</strong> · Due: {{ $action->due_date }} · State: <strong>{{ strtoupper($action->status) }}</strong></p>
<p class="mt-1 text-sm">Impact area: {{ $areas[$action->impact_area_code] ?? 'General / cross-functional' }} · Depends on: {{ $action->depends_on_action_id ? '#'.$action->depends_on_action_id : 'None' }}</p>
</section>
@empty<section class="mt-3 rounded-xl bg-white p-5">No proposed actions recorded yet.</section>@endforelse
@if($ccr->status === 'draft')
<section class="mt-6 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<h2 class="text-lg font-bold">Propose action</h2>
<form method="POST" action="{{ route('ccr.actions.store', $ccr->id) }}" class="mt-4 space-y-4">@csrf
<label class="block font-semibold">Title<input name="title" required minlength="5" maxlength="200" value="{{ old('title') }}" class="mt-1 w-full rounded-lg border p-2"></label>
<label class="block font-semibold">Description / intended deliverable<textarea name="description" required minlength="15" maxlength="10000" rows="4" class="mt-1 w-full rounded-lg border p-2" placeholder="Describe the proposed action; do not imply completion">{{ old('description') }}</textarea></label>
<label class="block font-semibold">Impact area (optional)<select name="impact_area_code" class="mt-1 w-full rounded-lg border p-2"><option value="">General / cross-functional</option>@foreach($areas as $key=>$label)<option value="{{ $key }}" @selected(old('impact_area_code')===$key)>{{ $label }}</option>@endforeach</select></label>
<label class="block font-semibold">Assigned owner<select name="owner_user_id" required class="mt-1 w-full rounded-lg border p-2"><option value="">Choose active owner</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((string)old('owner_user_id')===(string)$owner->id)>{{ $owner->name }}</option>@endforeach</select></label>
<label class="block font-semibold">Due date<input name="due_date" type="date" required min="{{ now()->toDateString() }}" value="{{ old('due_date') }}" class="mt-1 w-full rounded-lg border p-2"></label>
<label class="block font-semibold">Depends on existing action (optional)<select name="depends_on_action_id" class="mt-1 w-full rounded-lg border p-2"><option value="">No dependency</option>@foreach($actions as $other)<option value="{{ $other->id }}" @selected((string)old('depends_on_action_id')===(string)$other->id)>#{{ $other->id }} — {{ $other->title }}</option>@endforeach</select></label>
<button type="submit" class="rounded-lg px-5 py-3 font-semibold" style="background:#0f8b8d!important;color:#fff!important">Record proposed action</button>
</form></section>
@else<p class="mt-4 text-sm">CCR is locked. No new proposed actions may be created.</p>@endif
</main></body></html>
