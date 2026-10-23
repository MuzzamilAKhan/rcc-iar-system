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
        <div class="rounded border p-4">
            <label class="mb-2 block font-semibold">Affected SAP Products (1 to 30)</label>
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
