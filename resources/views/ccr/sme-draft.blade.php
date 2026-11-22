<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SME Draft | CMSoftware</title>@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
    <main class="mx-auto max-w-4xl p-6">
        <a href="{{ route('ccr.sme-drafts.index') }}" style="color:var(--ra-accent)">&larr; My CCR assessments</a>
        <h1 class="mt-5 text-2xl font-bold">{{ $areaLabel }} — SME draft</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $assignment->ccr_number }} · {{ $assignment->title }} · Draft only,
            not approved</p>
        <section class="mt-5 rounded-xl border bg-white p-5" style="border-color:var(--ra-stroke)">
            <h2 class="font-bold">Starter screening question</h2>
            <p class="mt-2">{{ $question }}</p>
        </section>
        @if(session('success'))
            <div class="mt-4 rounded-lg p-4" role="status" style="background:#dcfce7;color:#166534">{{ session('success') }}
        </div>@endif
        @if($errors->any())
            <div class="mt-4 rounded-lg p-4" role="alert" style="background:#fff1f2;color:#9f1239">
                <ul class="list-disc pl-5">@foreach($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
        </div>@endif
        @if($assignment->status === 'returned' && $assignment->review_comment)
            <section class="mt-4 rounded-xl border p-4" style="border-color:#e3aa62;background:#fff7e6;color:#704500">
                <strong>Returned for corrections:</strong>
                <p class="mt-2 whitespace-pre-wrap">{{ $assignment->review_comment }}</p>
            </section>
        @endif
        @if(in_array($assignment->status, ['submitted', 'reviewed'], true))
            <section class="mt-4 rounded-xl border p-4" style="border-color:var(--ra-stroke)">Assessment status:
                <strong>{{ strtoupper($assignment->status) }}</strong>. Editing locked.
        </section>@endif
        <form class="mt-5 space-y-4 rounded-xl bg-white p-5 method=" POST"
            action="{{ route('ccr.sme-drafts.save', $assignment->id) }}">@csrf
            <label class="block font-semibold">Screening answer
                <select required name="answer" class="mt-1 w-full rounded-lg border p-2">
                    <option value="">Choose answer</option>
                    @foreach(['yes' => 'Yes — potential effect', 'no' => 'No — no effect identified', 'uncertain' => 'Uncertain — more information required'] as $key => $label)
                        <option value="{{ $key }}" @selected(old('answer', $response?->answer) === $key)>{{ $label }}</option>
                    @endforeach
                </select></label>
            <label class="block font-semibold">Impact conclusion (draft)
                <select required name="impact_conclusion" class="mt-1 w-full rounded-lg border p-2">
                    <option value="">Choose conclusion</option>
                    @foreach(['potential_impact' => 'Potential impact', 'no_impact' => 'No impact identified', 'further_assessment' => 'Further assessment required'] as $key => $label)
                    <option value="{{ $key }}" @selected(old('impact_conclusion', $response?->impact_conclusion) === $key)>{{ $label }}</option>@endforeach
                </select></label>
            <label class="block font-semibold">Rationale / technical explanation
                <textarea required minlength="15" maxlength="10000" rows="6" name="rationale"
                    class="mt-1 w-full rounded-lg border p-2"
                    placeholder="Explain the basis for the draft conclusion">{{ old('rationale', $response?->rationale) }}</textarea></label>
            <label class="block font-semibold">Evidence references (optional)
                <textarea maxlength="5000" rows="3" name="evidence_references" class="mt-1 w-full rounded-lg border p-2"
                    placeholder="Controlled document IDs/revisions or evidence references; do not paste confidential content">{{ old('evidence_references', $response?->evidence_references) }}</textarea></label>
            @if(
                    $assignment->ccr_status === 'draft' &&
                    in_array($assignment->status, ['assigned', 'draft', 'returned'], true)
                )
                <button type="submit" class="rounded-lg px-5 py-3 font-semibold"
                    style="background:#0f8b8d!important;color:#fff!important">
                    Save SME draft
                </button>
            @else
                <p class="text-sm text-slate-600">
                    Assessment editing is locked.
                </p>
            @endif
        </form>
        @if($assignment->ccr_status === 'draft' && in_array($assignment->status, ['draft', 'returned'], true) && $response)
            <form class="mt-4" method="POST" action="{{ route('ccr.sme-reviews.submit', $assignment->id) }}">@csrf
                <button type="submit" class="rounded-lg px-5 py-3 font-semibold"
                    style="background:#0e6b83!important;color:#fff!important">Submit SME assessment for review</button>
        </form>@endif
        <p class="mt-4 text-sm text-slate-600">Independent review is not regulatory approval, QA/CRB approval, or
            implementation permission.</p>
    </main>
</body>

</html>
