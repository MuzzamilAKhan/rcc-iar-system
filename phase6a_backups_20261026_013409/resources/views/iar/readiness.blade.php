<!DOCTYPE html>
<html lang="en">
<head>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Submission Readiness | {{ $impactAssessment->iar_number }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
@include('partials.regatlas-navigation')

<main class="mx-auto max-w-6xl p-6">
    <a href="{{ route('iar.show', $impactAssessment) }}" class="text-blue-700">&larr; IAR details</a>
    <div class="mt-5 rounded-xl bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold">Submission Readiness</h1>
                <p class="mt-1 text-slate-600">{{ $report['iar_number'] }} &middot; Submission validation</p>
            </div>
            <span class="rounded px-3 py-1 text-sm {{ $report['can_submit'] ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                {{ $report['can_submit'] ? 'Ready to submit' : 'Blocked' }}
            </span>
        </div>
        <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-slate-50 p-4"><div class="text-2xl font-bold">{{ $report['product_count'] }}</div><div class="text-sm text-slate-600">Products</div></div>
            <div class="rounded-lg bg-slate-50 p-4"><div class="text-2xl font-bold">{{ $report['country_count'] }}</div><div class="text-sm text-slate-600">Countries</div></div>
            <div class="rounded-lg bg-slate-50 p-4"><div class="text-2xl font-bold">{{ $report['ready_count'] }}</div><div class="text-sm text-slate-600">Countries ready</div></div>
        </div>
        @if ($report['blockers'])
            <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4">
                <h2 class="font-semibold">Submission blockers</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                    @foreach ($report['blockers'] as $blocker)<li>{{ $blocker }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div class="mt-6 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-100"><tr><th class="p-3">Country</th><th class="p-3">Assignment strategy</th><th class="p-3">Proposed assignee</th><th class="p-3">Readiness / Reason</th></tr></thead>
                <tbody>
                @forelse ($report['countries'] as $country)
                    <tr class="border-b">
                        <td class="p-3 font-medium">{{ $country['country'] }}</td>
                        <td class="p-3">{{ str_replace('_', ' ', $country['strategy']) }}</td>
                        <td class="p-3">{{ $country['assignee'] ?? 'Not available' }}</td>
                        <td class="p-3">
                            @if ($country['ready'])<span class="font-medium text-green-700">Ready</span>
                            @else <ul class="list-disc pl-4 text-amber-800">@foreach ($country['blockers'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-6 text-slate-500">No countries identified.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($errors->any())
            <div class="mt-5 rounded border border-red-300 bg-red-50 p-4 text-red-800">
                <strong>Submission not completed:</strong>
                <ul class="mt-2 list-disc pl-5">@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($impactAssessment->status === 'draft' && $impactAssessment->creator_id === auth()->id() && auth()->user()->can('iar.create'))
            <form method="POST" action="{{ route('iar.submit', $impactAssessment) }}" class="mt-6">
                @csrf
                <button type="submit" @disabled(!$report['can_submit'])
                    style="{{ $report['can_submit'] ? 'background-color:#047857;color:white;' : 'background-color:#cbd5e1;color:#475569;' }}"
                    class="rounded-lg px-5 py-3 font-semibold disabled:cursor-not-allowed"
                    onclick="return confirm('Submit this IAR and assign every listed country? This action cannot be undone here.');">
                    Submit IAR &amp; Create Assignments
                </button>
                @unless($report['can_submit'])
                    <p class="mt-2 text-sm text-amber-800">Resolve all submission blockers before submission.</p>
                @endunless
            </form>
        @endif
        <p class="mt-6 text-sm text-slate-500">Submission creates country assignments only for eligible registered countries and locks draft editing. SAP exceptions remain recorded in the audit trail. Products with no registered countries do not generate assignments. Reminder notifications are not included in this release yet.</p>
    </div>
</main>
</body>
</html>
