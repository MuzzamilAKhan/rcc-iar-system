<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $impactAssessment->iar_number }} | RCC-IAR</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100">
    <main class="mx-auto max-w-5xl p-6">
        {{-- Navigation and actions --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-4">
                <a href="{{ route('dashboard') }}" class="text-blue-700 hover:underline">
                    &larr; Dashboard
                </a>
                <a href="{{ route('iar.index') }}" class="text-blue-700 hover:underline">
                    View all IARs
                </a>
            </div>
            @if ($impactAssessment->status === 'draft')
                <div class="flex flex-wrap items-center gap-3">
                    @if (
                            $impactAssessment->creator_id === auth()->id()
                            && auth()->user()->can('iar.create')
                        )
                        <a href="{{ route('iar.edit', $impactAssessment) }}"
                            class="rounded-lg border border-blue-600 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                            Edit Draft
                        </a>
                    @endif
                    <a href="{{ route('iar.readiness', ['impactAssessment' => $impactAssessment->id]) }}"
                        style="background-color: #047857; color: #ffffff;"
                        class="inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90">
                        Check Submission Readiness
                    </a>

                </div>
            @endif
        </div>
        {{-- Success message --}}
        @if (session('success'))
            <div class="my-5 rounded-lg bg-green-50 p-4 text-green-800">
                {{ session('success') }}
            </div>
        @endif
        {{-- IAR details --}}
        <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold">
                    {{ $impactAssessment->iar_number }}
                </h1>
                <span class="rounded bg-amber-100 px-3 py-1 text-amber-900">
                    {{ ucfirst($impactAssessment->status) }}
                </span>
            </div>
            {{-- Basic information --}}
            <dl class="mt-6 grid gap-4 md:grid-cols-2">
                <div>
                    <dt class="text-sm text-slate-500">
                        CCR Number
                    </dt>
                    <dd class="font-medium">
                        {{ $impactAssessment->ccr_number }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">
                        Created By
                    </dt>
                    <dd>
                        {{ $impactAssessment->creator?->name }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">
                        Due At
                    </dt>
                    <dd>
                        {{ $impactAssessment->due_at?->format('Y-m-d H:i') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">
                        Implementation Date
                    </dt>
                    <dd>
                        {{ $impactAssessment->estimated_implementation_date?->format('Y-m-d') ?? 'Not specified' }}
                    </dd>
                </div>
            </dl>
            {{-- Current version --}}
            <div class="mt-5">
                <h2 class="font-semibold">
                    Current Version / Situation
                </h2>
                <p class="mt-1 whitespace-pre-wrap">
                    {{ $impactAssessment->current_version }}
                </p>
            </div>
            {{-- New version --}}
            <div class="mt-5">
                <h2 class="font-semibold">
                    New Version / Change
                </h2>
                <p class="mt-1 whitespace-pre-wrap">
                    {{ $impactAssessment->new_version }}
                </p>
            </div>
            {{-- Impact flags --}}
            <div class="mt-5">
                <h2 class="font-semibold">
                    Impact Flags
                </h2>
                <p class="mt-1">
                    SAP / UDI / ICC:
                    {{ $impactAssessment->sap_udi_icc_impacted ? 'Yes' : 'No' }}
                    <span class="mx-2 text-slate-400">|</span>
                    Labelling:
                    {{ $impactAssessment->labelling_impacted ? 'Yes' : 'No' }}
                    <span class="mx-2 text-slate-400">|</span>
                    Urgent:
                    {{ $impactAssessment->is_urgent ? 'Yes' : 'No' }}
                </p>
            </div>
            {{-- Affected products --}}
            <div class="mt-5">
                <h2 class="font-semibold">
                    Affected Products
                </h2>
                <ul class="mt-2 list-disc pl-5">
                    @forelse ($impactAssessment->products as $item)
                        <li class="mb-1">
                            {{ $item->submitted_sap_code }}
                            &mdash;
                            {{ $item->product?->product_name ?? 'Product unavailable' }}
                        </li>
                    @empty
                        <li class="text-slate-500">
                            No affected products selected.
                        </li>
                    @endforelse
                </ul>
            </div>
            {{-- Workflow information --}}
            <div class="mt-6 border-t border-slate-200 pt-5">
                @if ($impactAssessment->status === 'draft')
                    <p class="text-sm text-slate-500">
                        This IAR is currently a draft. Use
                        <strong>Check Submission Readiness</strong>
                        to review country assignments and submission blockers
                        before proceeding.
                    </p>
                @else
                    <p class="text-sm text-slate-500">
                        This IAR is currently
                        {{ ucfirst($impactAssessment->status) }}.
                    </p>
                @endif
                <p class="mt-2 text-xs text-slate-400">
                    Submission, country assignment creation and notifications
                    are not yet enabled.
                </p>
            </div>
        </div>
    </main>
</body>

</html>
