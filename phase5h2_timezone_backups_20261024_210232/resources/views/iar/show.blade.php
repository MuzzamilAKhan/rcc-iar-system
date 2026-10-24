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
                            {{ $item->product?->product_name ?? 'UNRESOLVED — not found in Product Registry' }}
                        </li>
                    @empty
                        <li class="text-slate-500">
                            No affected products selected.
                        </li>
                    @endforelse
                </ul>
            </div>
            @if ($impactAssessment->products->contains(fn ($item) => $item->product_id === null))
                <div class="mt-4 rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    Unresolved SAP codes are retained for traceability. Submission remains blocked until applicable country assignment and registration issues are resolved.
                </div>
            @endif
            {{-- Phase 5H.2: read-only exception evidence for authorized IAR viewers. --}}
            @php
                $overrideAudits = \Illuminate\Support\Facades\DB::table('audit_logs')
                    ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
                    ->where('audit_logs.entity_type', \App\Models\ImpactAssessment::class)
                    ->where('audit_logs.entity_id', $impactAssessment->id)
                    ->whereIn('audit_logs.action', ['iar.sap.override.created', 'iar.sap.override.updated'])
                    ->select('audit_logs.*', 'users.name as actor_name')
                    ->orderBy('audit_logs.id')->get();
                $linkedProductIds = $impactAssessment->products->pluck('product_id')->filter()->unique();
                $withoutRegistration = $linkedProductIds->isEmpty() ? collect() : \Illuminate\Support\Facades\DB::table('products')
                    ->whereIn('id', $linkedProductIds)
                    ->whereNotIn('id', \Illuminate\Support\Facades\DB::table('product_registrations')->select('product_id'))
                    ->orderBy('sap_code')->pluck('sap_code');
            @endphp
            @if ($withoutRegistration->isNotEmpty())
                <section class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4">
                    <h2 class="font-semibold text-amber-900">Missing Country Registration Data</h2>
                    <p class="mt-1 text-sm text-amber-900">These SAP codes exist in the Product Registry but have no registration records. Submission is blocked until registration data is reviewed and corrected.</p>
                    <ul class="mt-2 list-disc pl-5 text-sm text-amber-900">
                        @foreach ($withoutRegistration as $code)<li>{{ $code }}</li>@endforeach
                    </ul>
                </section>
            @endif
            @if ($overrideAudits->isNotEmpty())
                <section class="mt-5 rounded-lg border p-4">
                    <h2 class="font-semibold">SAP Override Audit History</h2>
                    <p class="mt-1 text-xs text-slate-600">Historical decisions are retained even if the registry is later corrected.</p>
                    @foreach ($overrideAudits as $entry)
                        @php $evidence = json_decode($entry->new_values ?? '{}', true) ?: []; @endphp
                        <div class="mt-3 border-t pt-3 text-sm">
                            {{-- <p><strong>{{ $entry->actor_name ?? 'Former user' }}</strong> — {{ $entry->created_at }} — {{ $entry->action }}</p> --}}
                            <p><strong>{{ $entry->actor_name ?? 'Former user' }}</strong> — {{ \Carbon\Carbon::parse($entry->created_at, 'UTC')->setTimezone('Asia/Karachi')->format('Y-m-d H:i:s') }} PKT — {{ $entry->action }}</p>
                            <p class="mt-1 whitespace-pre-wrap"><strong>Justification:</strong> {{ $evidence['reason'] ?? 'Not available' }}</p>
                            <p class="mt-1"><strong>Unresolved SAP codes:</strong> {{ implode(', ', $evidence['unresolved_sap_codes'] ?? []) }}</p>
                            @if (!empty($evidence['unresolved_added']))<p>Added: {{ implode(', ', $evidence['unresolved_added']) }}</p>@endif
                            @if (!empty($evidence['unresolved_removed']))<p>Removed: {{ implode(', ', $evidence['unresolved_removed']) }}</p>@endif
                        </div>
                    @endforeach
                </section>
            @endif
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
