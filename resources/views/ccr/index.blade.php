<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Change Control | CMSoftware</title>@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">@include('partials.regatlas-navigation')
    <main class="mx-auto max-w-5xl p-6">
        <h1 class="text-2xl font-bold">Change Control Records</h1>
        <p class="mt-2 text-sm text-slate-600">CCR foundation — draft records only. Approvals and AI assessments are not
            yet enabled.</p>
        @if(auth()->user()->can('iar.create') || auth()->user()->hasRole('super-admin'))
            <a href="{{ route('ccr.create') }}"
                class="mt-4 inline-block rounded-lg px-5 py-2.5 font-semibold shadow-sm transition-colors"
                style="background-color: #0f8b8d !important; color: #ffffff !important; text-decoration: none;">
                + Create CCR
            </a>
        @endif
        <div class="mt-6 rounded-xl bg-white p-5 shadow-sm">@forelse($records as $record)
            <a class="block border-b p-3 hover:bg-slate-50"
                href="{{ route('ccr.show', $record->id) }}"><strong>{{ $record->ccr_number }}</strong> —
                {{ $record->title }} <span class="text-sm text-slate-500">({{ $record->status }})</span></a>
        @empty<p>No change control records available for your account.</p>@endforelse
        </div>
        <div class="mt-4">{{ $records->links() }}</div>
    </main>
</body>

</html>
