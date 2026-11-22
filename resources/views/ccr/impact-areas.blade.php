<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Impact Areas | {{ $record->ccr_number }}</title>@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
    <main class="mx-auto max-w-5xl p-6">
        <a href="{{ route('ccr.show', $record->id) }}" style="color:var(--ra-accent)">&larr;
            {{ $record->ccr_number }}</a>
        <h1 class="mt-5 text-2xl font-bold">Cross-functional impact selection</h1>
        <p class="mt-2 text-sm text-slate-600">{{ $record->title }} — choose an explicit decision for all 16 areas.
            Suggested areas are rule-based hints, not AI conclusions. Regulatory pathway screening will be a separate
            mandatory later stage.</p>
        @if(session('success'))
            <div role="status" class="mt-4 rounded-lg p-4" style="background:#dcfce7;color:#166534">{{ session('success') }}
        </div>@endif
        @if($errors->any())
            <div role="alert" class="mt-4 rounded-lg border p-4"
                style="background:#fff1f2;color:#9f1239;border-color:#fecdd3"><strong>Please fix:</strong>
                <ul class="list-disc pl-6">@foreach($errors->all() as $error)
                <li>{{ $error }}</li>@endforeach
                </ul>
        </div>@endif
        <form class="mt-5 space-y-3" method="POST" action="{{ route('ccr.impacts.update', $record->id) }}">@csrf
            @foreach($areas as $code => $label)
            @php($entry = $decisions->get($code))
            @php($chosen = old('decisions.' . $code, $entry?->decision ?? ''))
            <section class="rounded-xl border bg-white p-4" style="border-color:var(--ra-stroke)">
                <div class="flex flex-wrap items-center gap-3"><strong>{{ $loop->iteration }}. {{ $label }}</strong>
                    @if(in_array($code, $suggested, true))<span class="rounded-full px-2 py-1 text-xs font-semibold"
                    style="background:#fff3cf;color:#704500">Suggested for consideration</span>@endif
                </div>
                <div class="mt-3 flex flex-wrap gap-5 text-sm">
                    <label><input type="radio" name="decisions[{{ $code }}]" value="selected"
                            @checked($chosen === 'selected') required> Applicable — select</label>
                    <label><input type="radio" name="decisions[{{ $code }}]" value="not_selected"
                            @checked($chosen === 'not_selected') required> Not applicable</label>
                </div>
                <label class="mt-3 block text-sm">Rationale / exclusion explanation
                    <textarea name="rationales[{{ $code }}]" maxlength="2000" rows="2"
                        class="mt-1 w-full rounded-lg border p-2"
                        placeholder="Explain selection or exclusion, where appropriate">{{ old('rationales.' . $code, $entry?->rationale ?? '') }}</textarea>
                </label>
                @if(in_array($code, $suggested, true) && in_array($code, ['risk', 'regulatory', 'sterilisation'], true))
                    <p class="mt-1 text-xs" style="color:var(--ra-fg-muted)">Excluding this suggested significant area
                requires a rationale.</p>@endif
            </section>
            @endforeach
            <button type="submit" class="rounded-lg px-5 py-3 font-semibold shadow-sm"
                style="background-color:#0f8b8d !important; color:#ffffff !important; cursor:pointer;"
                @disabled($record->status !== 'draft')>
                Save impact decisions
            </button>
            @if($record->status !== 'draft')
            <p class="text-sm">CCR is no longer a draft; decisions are locked.</p>@endif
        </form>
    </main>
</body>

</html>
