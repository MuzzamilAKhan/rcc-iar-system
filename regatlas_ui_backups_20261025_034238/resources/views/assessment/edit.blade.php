<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assessment Draft | RCC-IAR</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900"><main class="mx-auto max-w-3xl p-6">
<a href="{{ route('assessment.index') }}" class="text-blue-700">&larr; My Assigned Assessments</a>
<div class="mt-5 rounded-xl bg-white p-6 shadow-sm">
<h1 class="text-2xl font-bold">Assessment: {{ $assignment->impactAssessment?->iar_number }}</h1>
<p class="mt-1 text-slate-600">{{ $assignment->country?->name }} · Assignment {{ ucfirst($assignment->status) }}</p>
@if (session('success')) <p class="mt-4 rounded bg-green-50 p-3 text-green-800">{{ session('success') }}</p> @endif
@if ($errors->any())<div class="mt-4 rounded bg-red-50 p-3 text-red-800"><ul class="list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@php $response = $assignment->response; $locked = $assignment->status !== 'assigned' || ($response && $response->status !== 'draft'); @endphp
@if($locked)<p class="mt-5 rounded bg-amber-50 p-3 text-amber-900">This assessment is locked for editing.</p>@endif
<form method="POST" action="{{ route('assessment.save', $assignment) }}" class="mt-6 space-y-4">@csrf
<div><label for="impact_type" class="block font-semibold">Impact type</label><input id="impact_type" name="impact_type" maxlength="40" value="{{ old('impact_type', $response?->impact_type) }}" @disabled($locked) class="mt-1 w-full rounded border p-2" placeholder="e.g. Regulatory / Labelling"></div>
<div><label for="timeline" class="block font-semibold">Timeline</label><textarea id="timeline" name="timeline" rows="3" @disabled($locked) class="mt-1 w-full rounded border p-2">{{ old('timeline', $response?->timeline) }}</textarea></div>
<div><label for="action_items" class="block font-semibold">Action items</label><textarea id="action_items" name="action_items" rows="4" @disabled($locked) class="mt-1 w-full rounded border p-2">{{ old('action_items', $response?->action_items) }}</textarea></div>
<div><label for="comments" class="block font-semibold">Comments</label><textarea id="comments" name="comments" rows="4" @disabled($locked) class="mt-1 w-full rounded border p-2">{{ old('comments', $response?->comments) }}</textarea></div>
@foreach (['stop_ship_required' => 'Stop ship required', 'more_information_required' => 'More information required', 'changed_product_imported' => 'Changed product imported'] as $name => $label)
<div><label class="inline-flex items-center gap-2"><input type="hidden" name="{{ $name }}" value="0" @disabled($locked)><input type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $response?->$name)) @disabled($locked)><span>{{ $label }}</span></label></div>
@endforeach
@if(!$locked)<button type="submit" style="background:#1d4ed8;color:#fff" class="rounded px-5 py-2 font-semibold">Save Draft Assessment</button>@endif
</form><p class="mt-5 text-sm text-slate-500">This saves a draft only. Final assessment submission will be implemented in Phase 5D.2.</p>
</div></main></body></html>
