<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $impactAssessment->iar_number }} | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">
    <main class="mx-auto max-w-5xl p-6">
        <div class="flex flex-wrap gap-4"><a href="{{ route('dashboard') }}" class="text-blue-700">&larr; Dashboard</a>
        <a href="{{ route('iar.index') }}" class="text-blue-700">View all IARs</a>
        @if($impactAssessment->status === 'draft' && $impactAssessment->creator_id === auth()->id() && auth()->user()->can('iar.create'))
            <a href="{{ route('iar.edit', $impactAssessment) }}" class="font-semibold text-blue-700">Edit Draft</a>
        @endif</div>
        @if (session('success'))
        <div class="my-5 rounded bg-green-50 p-4 text-green-800">{{ session('success') }}</div> @endif
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">{{ $impactAssessment->iar_number }}</h1>
                <span
                    class="rounded bg-amber-100 px-3 py-1 text-amber-900">{{ ucfirst($impactAssessment->status) }}</span>
            </div>
            <dl class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <dt class="text-sm text-slate-500">CCR Number</dt>
                    <dd class="font-medium">{{ $impactAssessment->ccr_number }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Created By</dt>
                    <dd>{{ $impactAssessment->creator?->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Due At</dt>
                    <dd>{{ $impactAssessment->due_at?->format('Y-m-d H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Implementation Date</dt>
                    <dd>{{ $impactAssessment->estimated_implementation_date?->format('Y-m-d') ?? 'Not specified' }}</dd>
                </div>
            </dl>
            <div class="mt-5">
                <h2 class="font-semibold">Current Version / Situation</h2>
                <p class="mt-1 whitespace-pre-wrap">{{ $impactAssessment->current_version }}</p>
            </div>
            <div class="mt-5">
                <h2 class="font-semibold">New Version / Change</h2>
                <p class="mt-1 whitespace-pre-wrap">{{ $impactAssessment->new_version }}</p>
            </div>
            <div class="mt-5">
                <h2 class="font-semibold">Impact Flags</h2>
                <p>SAP / UDI / ICC: {{ $impactAssessment->sap_udi_icc_impacted ? 'Yes' : 'No' }} ┬╖ Labelling:
                    {{ $impactAssessment->labelling_impacted ? 'Yes' : 'No' }} ┬╖ Urgent:
                    {{ $impactAssessment->is_urgent ? 'Yes' : 'No' }}</p>
            </div>
            <div class="mt-5">
                <h2 class="font-semibold">Affected Products</h2>
                <ul class="mt-2 list-disc pl-5">
                    @foreach ($impactAssessment->products as $item)
                        <li>{{ $item->submitted_sap_code }} ΓÇö {{ $item->product?->product_name ?? 'Product unavailable' }}
                        </li>
                    @endforeach
                </ul>
            </div>
            <p class="mt-6 text-sm text-slate-500">Draft saved. Submission, assessment assignment and notification
                workflows are not yet implemented.</p>
        </div>
    </main>
</body>

</html>
