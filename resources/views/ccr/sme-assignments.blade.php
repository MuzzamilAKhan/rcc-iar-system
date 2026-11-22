<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>SME Assignments | {{ $record->ccr_number }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
<main class="mx-auto max-w-5xl p-6">
<a href="{{ route('ccr.show', $record->id) }}" style="color:var(--ra-accent)">&larr; {{ $record->ccr_number }}</a>
<h1 class="mt-5 text-2xl font-bold">SME assessment assignments</h1>
<p class="mt-2 text-sm text-slate-600">Assign a qualified active RA user to each selected area. Starter screening questions are reference prompts only; detailed SME responses can be submitted for independent review. No CCR approval is granted here. No emails are sent.</p>
@if(session('success'))<div class="mt-4 rounded-lg p-4" role="status" style="background:#dcfce7;color:#166534">{{ session('success') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-lg border p-4" role="alert" style="background:#fff1f2;color:#9f1239;border-color:#fecdd3">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@if(empty($areas))<section class="mt-5 rounded-xl bg-white p-5">No applicable impact areas selected yet. <a href="{{ route('ccr.impacts.edit', $record->id) }}" style="color:var(--ra-accent)">Select impact areas first</a>.</section>@endif
@foreach($areas as $code => $label)
@php($assignment = $assignments->get($code))
<section class="mt-4 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
<h2 class="text-lg font-bold">{{ $label }}</h2>
<p class="mt-2 text-sm"><strong>Starter question:</strong> {{ $questions[$code] ?? 'Review the proposed change and provide an area-specific assessment.' }}</p>
@if($assignment)
<p class="mt-3 text-sm"><strong>Assigned SME:</strong> {{ $assignment->assessor_name }} · Status: {{ $assignment->status }}</p>
@if((int) $assignment->assigned_to_user_id === (int) auth()->id())<a class="mt-2 inline-block rounded-lg px-4 py-2 font-semibold" style="background:#0f8b8d!important;color:#fff!important" href="{{ route('ccr.sme-drafts.edit', $assignment->id) }}">Open SME draft</a>@endif
@else
<form method="POST" action="{{ route('ccr.sme.store', $record->id) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
<input type="hidden" name="area_code" value="{{ $code }}">
<label class="text-sm font-semibold">Assign SME
<select name="assigned_to_user_id" required class="mt-1 block min-w-64 rounded-lg border p-2"><option value="">Select active RA user</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select>
</label>
<button type="submit" @disabled($record->status !== 'draft') class="rounded-lg px-4 py-2 font-semibold" style="background:#0f8b8d!important;color:#fff!important">Assign SME</button>
</form>
@endif
</section>
@endforeach
</main></body></html>
