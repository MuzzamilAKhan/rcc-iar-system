<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pending Country Assignments | RegAtlas</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
@include('partials.regatlas-navigation')
<main class="mx-auto max-w-6xl p-6">
<a href="{{ route('iar.show', $impactAssessment) }}" class="text-blue-700">&larr; IAR details</a>
<section class="mt-5 rounded-xl bg-white p-6 shadow-sm">
<h1 class="text-2xl font-bold">Pending Country Assignments</h1>
<p class="mt-1 text-sm text-slate-500">{{ $impactAssessment->iar_number }} · Original due date: {{ $impactAssessment->due_at?->format('Y-m-d H:i') }} UTC</p>
<p class="mt-2 text-sm text-slate-500">Only the currently eligible primary Local RA can be assigned. Update the approved assessor mapping in master data first. Assignment does not mark an assessment completed or extend the IAR deadline.</p>
@if(session('success'))<p class="mt-4 rounded-lg border p-3">{{ session('success') }}</p>@endif
@if($errors->any())<div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-amber-900">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<div class="mt-6 overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b"><th class="p-3">Country</th><th class="p-3">Status</th><th class="p-3">Primary mapping</th><th class="p-3">Reason / Action</th></tr></thead><tbody>
@forelse($exceptions as $exception)
<tr class="border-b"><td class="p-3 font-medium">{{ $exception->country }}</td><td class="p-3">{{ ucfirst($exception->resolution_status) }}</td>
<td class="p-3">{{ $exception->mapped_assessor ?? 'Not mapped' }}</td><td class="p-3">
@if($exception->resolution_status === 'open')
<p class="mb-2 text-amber-800">{{ $exception->details }}</p>
<form method="POST" action="{{ route('iar.deferred.resolve', [$impactAssessment, $exception->id]) }}">@csrf
<button type="submit" class="rounded-lg border px-4 py-2 font-semibold" onclick="return confirm('Assign using the current eligible primary Local RA mapping? Original deadline will remain unchanged.');">Resolve pending assignment</button>
</form>
@else <span class="text-green-700">Resolved and audited</span>@endif
</td></tr>
@empty<tr><td colspan="4" class="p-5">No deferred country assignments on this IAR.</td></tr>@endforelse
</tbody></table></div></section></main></body></html>
