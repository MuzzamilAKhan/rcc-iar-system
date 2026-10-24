<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Registry Import Requests</title>@vite(['resources/css/app.css','resources/js/app.js'])</head><body class="bg-slate-100 p-7">
@include('partials.regatlas-navigation')
<main class="mx-auto max-w-6xl rounded-xl bg-white p-7"><a href="{{ route('admin.products') }}" class="text-blue-700">&larr; Product Registry</a><h1 class="my-5 text-2xl font-bold">Registry Import Approval Queue</h1>@php
 $viewerTimezone = auth()->user()?->timezone ?: 'UTC';
 $uploaderNames = \App\Models\User::query()->whereIn('id', $requests->pluck('uploaded_by')->all())->pluck('name', 'id');
@endphp
<p class="mb-3 text-sm text-slate-600">Times displayed in your timezone: <strong>{{ $viewerTimezone }}</strong></p>
<form method="GET" action="{{ route('admin.products.import.requests') }}" class="mb-5 flex flex-wrap items-end gap-3 rounded border border-slate-200 p-4 text-sm">
 <label>Search ID / file / user <input name="search" value="{{ request('search') }}" maxlength="150" class="block rounded border p-2" placeholder="Search import history"></label>
 <label>Status <select name="status" class="block rounded border p-2"><option value="">All statuses</option>@foreach(['pending','approved','rejected'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>@endforeach</select></label>
 <label>From (UTC date) <input type="date" name="from" value="{{ request('from') }}" class="block rounded border p-2"></label>
 <label>To (UTC date) <input type="date" name="to" value="{{ request('to') }}" class="block rounded border p-2"></label>
 <button class="rounded bg-blue-700 px-4 py-2 text-white" type="submit">Apply Filters</button>
 <a href="{{ route('admin.products.import.requests') }}" class="text-blue-700 underline">Clear</a>
 <a href="{{ route('admin.products.import.evidence', request()->only(['search','status','from','to'])) }}" class="rounded border border-blue-700 px-4 py-2 text-blue-700">Export Audit Evidence (.xlsx)</a>
</form>
<p class="mb-3 text-xs text-slate-500">Filters use UTC dates; displayed timestamps use your chosen timezone. Evidence export timestamps are UTC.</p>
<table class="w-full text-left text-sm"><thead><tr><th>ID</th><th>Uploaded By</th><th>File</th><th>Rows</th><th>Status</th><th>Created</th></tr></thead><tbody>@foreach($requests as $batch)<tr class="border-t"><td class="py-3"><a class="text-blue-700 underline" href="{{ route('admin.products.import.requests.show',$batch->id) }}">#{{ $batch->id }}</a></td><td>{{ $uploaderNames[$batch->uploaded_by] ?? ('Former user #'.$batch->uploaded_by) }}</td><td>{{ $batch->filename }}</td><td>{{ $batch->total_rows }}</td><td>{{ $batch->status }}</td><td>{{ \Illuminate\Support\Carbon::parse($batch->created_at, 'UTC')->timezone($viewerTimezone)->format('Y-m-d H:i:s T') }}</td></tr>@endforeach</tbody></table><div class="mt-6">{{ $requests->links() }}</div></main></body></html>