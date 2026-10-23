<div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
    <h1 class="mb-2 text-2xl font-bold">Edit Draft: {{ $iar->iar_number }}</h1>
    <p class="mb-6 text-sm text-amber-700">Only drafts can be edited. Saving records an audit event.</p>
    @error('draft')<p class="mb-4 text-red-700">{{ $message }}</p>@enderror
    <form wire:submit="save" class="space-y-5">
        <div><label class="mb-1 block font-medium">CCR Number</label><input wire:model="ccrNumber" class="w-full rounded border p-3">@error('ccr_number')<p class="text-red-700">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1 block font-medium">Current Version / Situation</label><textarea wire:model="currentVersion" rows="4" class="w-full rounded border p-3"></textarea>@error('current_version')<p class="text-red-700">{{ $message }}</p>@enderror</div>
        <div><label class="mb-1 block font-medium">New Version / Change</label><textarea wire:model="newVersion" rows="4" class="w-full rounded border p-3"></textarea>@error('new_version')<p class="text-red-700">{{ $message }}</p>@enderror</div>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label class="mb-1 block font-medium">Due Date / Time</label><input type="datetime-local" wire:model="dueAt" class="w-full rounded border p-3">@error('due_at')<p class="text-red-700">{{ $message }}</p>@enderror</div>
            <div><label class="mb-1 block font-medium">Estimated Implementation Date</label><input type="date" wire:model="estimatedImplementationDate" class="w-full rounded border p-3">@error('estimated_implementation_date')<p class="text-red-700">{{ $message }}</p>@enderror</div>
        </div>
        <div class="flex flex-wrap gap-5">
            <label><input type="checkbox" wire:model="sapUdiIccImpacted"> SAP / UDI / ICC impacted</label>
            <label><input type="checkbox" wire:model="labellingImpacted"> Labelling impacted</label>
            <label><input type="checkbox" wire:model="isUrgent"> Urgent</label>
        </div>
        <section class="rounded border border-blue-200 bg-blue-50 p-4">
            <h3 class="font-semibold">Replace affected products using Excel SAP codes</h3>
            <p class="mt-1 text-sm text-slate-600">Optional: Paste one SAP code per row. When provided, this list replaces all currently selected products on Save. Missing or ambiguous SAP codes block saving. Leave blank to keep using the checkbox selection below.</p>
            <textarea wire:model="bulkSapInput" rows="6" class="mt-3 w-full rounded border p-3 font-mono text-sm" placeholder="MOCK-00001&#10;MOCK-00002"></textarea>
            @error('bulkSapInput')<p class="mt-2 text-sm text-red-700">{{ $message }}</p>@enderror
            <button type="button" wire:click="previewBulkSap" wire:loading.attr="disabled" wire:target="previewBulkSap" class="mt-2 rounded px-4 py-2 text-white" style="background-color:#1d4ed8">Validate pasted codes</button>
            @if ($bulkSapReport !== null)
                <p class="mt-3 text-sm">{{ $bulkSapReport['matched_count'] }} matched; {{ $bulkSapReport['missing_count'] }} missing; {{ $bulkSapReport['ambiguous_count'] }} ambiguous; {{ $bulkSapReport['duplicate_count'] }} duplicates.</p>
                @if ($bulkSapReport['missing_preview'])<p class="text-sm text-amber-800">Missing: {{ implode(', ', $bulkSapReport['missing_preview']) }}</p>@endif
                @if ($bulkSapReport['ambiguous_preview'])<p class="text-sm text-amber-800">Ambiguous: {{ implode(', ', $bulkSapReport['ambiguous_preview']) }}</p>@endif
            @endif
        </section>
        <div class="rounded border p-4">
            <label class="mb-2 block font-semibold">Affected SAP Products (manual selection)</label>
            <input type="search" wire:model.live.debounce.400ms="productSearch" placeholder="Search SAP code or product name" class="mb-3 w-full rounded border p-3">
            <div class="mb-3"><h3 class="text-sm font-semibold">Selected products</h3>
                @forelse($selectedProducts as $product)
                    <div wire:key="selected-product-{{ $product->id }}" class="text-sm">{{ $product->sap_code }} — {{ $product->product_name }}</div>
                @empty<p class="text-sm text-slate-500">No products selected.</p>@endforelse
            </div>
            <div class="max-h-72 space-y-2 overflow-y-auto">
                @foreach($products as $product)
                    <label wire:key="product-{{ $product->id }}" class="block text-sm">
                        <input type="checkbox" wire:model.live="selectedProductIds" value="{{ $product->id }}">
                        {{ $product->sap_code }} — {{ $product->product_name }}
                    </label>
                @endforeach
            </div>
            @error('product_ids')<p class="text-red-700">{{ $message }}</p>@enderror
            @error('product_ids.*')<p class="text-red-700">{{ $message }}</p>@enderror
        </div>
        <div class="flex gap-3"><button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded bg-blue-700 px-5 py-3 text-white disabled:opacity-50">Save Draft Changes</button>
            <a href="{{ route('iar.show', $iar) }}" class="rounded border px-5 py-3">Cancel</a></div>
    </form>
</div>
