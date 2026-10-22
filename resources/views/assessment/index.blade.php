<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Assigned Assessments | RCC-IAR</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="min-h-screen bg-slate-100 text-slate-900"><main class="mx-auto max-w-6xl p-6">
<a class="text-blue-700" href="{{ route('dashboard') }}">&larr; Dashboard</a>
<div class="mt-5 rounded-xl bg-white p-6 shadow-sm">
<h1 class="text-2xl font-bold">My Assigned Assessments</h1>
<p class="mt-1 text-sm text-slate-600">Only IAR country assignments allocated to your account are shown.</p>
<div class="mt-6 overflow-x-auto"><table class="w-full text-left text-sm">
<thead class="bg-slate-100"><tr><th class="p-3">IAR</th><th class="p-3">Country</th><th class="p-3">Due</th><th class="p-3">Assignment</th><th class="p-3">Response</th><th class="p-3">Action</th></tr></thead><tbody>
@forelse ($assignments as $assignment)
<tr class="border-b"><td class="p-3">{{ $assignment->impactAssessment?->iar_number }}</td>
<td class="p-3">{{ $assignment->country?->name }}</td>
<td class="p-3">{{ $assignment->due_at?->format('Y-m-d H:i') }}</td>
<td class="p-3">{{ ucfirst($assignment->status) }}</td>
<td class="p-3">{{ ucfirst($assignment->response?->status ?? 'Not started') }}</td>
<td class="p-3">@if ($assignment->impactAssessment?->status === 'submitted' && auth()->user()->can('assessment.edit.assigned'))
<a class="text-blue-700 hover:underline" href="{{ route('assessment.edit', $assignment) }}">{{ $assignment->response?->status === 'submitted' ? 'View assessment' : 'Fill assessment' }}</a>
@else <span class="text-slate-500">Unavailable</span> @endif</td></tr>
@empty<tr><td colspan="6" class="p-6 text-slate-500">No assigned assessments yet.</td></tr>@endforelse
</tbody></table></div><div class="mt-4">{{ $assignments->links() }}</div></div></main></body></html>
