<div class="mt-5 rounded-xl bg-white p-6 shadow-sm">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">View IARs</h1>
        @can('iar.create')<a href="{{ route('iar.create') }}" class="rounded bg-blue-700 px-4 py-2 text-white">Create IAR</a>@endcan
    </div>
    <div class="mb-5 grid gap-3 md:grid-cols-2">
        <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search IAR or CCR number" class="rounded border p-3">
        <select wire:model.live="status" class="rounded border p-3">
            <option value="">All statuses</option>
            <option value="draft">Draft</option><option value="submitted">Submitted</option><option value="closed">Closed</option>
        </select>
    </div>
    <div class="overflow-x-auto"><table class="w-full text-left text-sm">
        <thead><tr class="border-b bg-slate-50"><th class="p-3">IAR Number</th><th class="p-3">CCR</th><th class="p-3">Creator</th><th class="p-3">Products</th><th class="p-3">Status</th><th class="p-3">Actions</th></tr></thead>
        <tbody>
        @forelse($iars as $iar)
            <tr wire:key="iar-{{ $iar->id }}" class="border-b">
                <td class="p-3 font-semibold">{{ $iar->iar_number }}</td><td class="p-3">{{ $iar->ccr_number }}</td>
                <td class="p-3">{{ $iar->creator?->name }}</td><td class="p-3">{{ $iar->products_count }}</td>
                <td class="p-3">{{ ucfirst($iar->status) }}</td>
                <td class="p-3 whitespace-nowrap"><a href="{{ route('iar.show', $iar) }}" class="text-blue-700">View</a>
                    @if($iar->status === 'draft' && $iar->creator_id === auth()->id() && auth()->user()->can('iar.create'))
                        <a href="{{ route('iar.edit', $iar) }}" class="ml-3 text-blue-700">Edit Draft</a>
                    @endif
                </td>
            </tr>
        @empty<tr><td colspan="6" class="p-8 text-center text-slate-500">No IAR records found.</td></tr>@endforelse
        </tbody></table></div>
    <div class="mt-5">{{ $iars->links() }}</div>
</div>
