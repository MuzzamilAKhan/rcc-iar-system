<div class="rounded-xl bg-white p-6 shadow-sm">
    <h2 class="mb-1 text-xl font-semibold">New IAR Draft</h2>
    <p class="mb-6 text-sm text-slate-500">Draft only — no assignments or notifications will be issued.</p>

    <form wire:submit="save" class="space-y-5">
        <div>
            <label for="ccr-number" class="block text-sm font-medium">CCR Number *</label>
            <input id="ccr-number" type="text" wire:model="ccrNumber" maxlength="100"
                class="mt-1 w-full rounded-lg border p-3" placeholder="CCR-2026-001">
            @error('ccr_number')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="current-version" class="block text-sm font-medium">Current Version / Situation *</label>
                <textarea id="current-version" wire:model="currentVersion" rows="4"
                    class="mt-1 w-full rounded-lg border p-3"></textarea>
                @error('current_version')
                <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="new-version" class="block text-sm font-medium">New Version / Proposed Change *</label>
                <textarea id="new-version" wire:model="newVersion" rows="4"
                    class="mt-1 w-full rounded-lg border p-3"></textarea>
                @error('new_version')
                <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <fieldset class="rounded-lg border p-4">
            <legend class="px-1 font-semibold">Change Impact</legend>
            <div class="space-y-2 text-sm">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="sapUdiIccImpacted"> SAP / UDI
                    / ICC impacted</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="labellingImpacted"> Labelling
                    impacted</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="isUrgent"> Urgent
                    change</label>
            </div>
        </fieldset>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="due-at" class="block text-sm font-medium">Due Date & Time *</label>
                <input id="due-at" type="datetime-local" wire:model="dueAt" class="mt-1 w-full rounded-lg border p-3">
                @error('due_at')
                <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="implementation-date" class="block text-sm font-medium">Estimated Implementation Date</label>
                <input id="implementation-date" type="date" wire:model="estimatedImplementationDate"
                    class="mt-1 w-full rounded-lg border p-3">
                @error('estimated_implementation_date')
                <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            </div>
        </div>
        <section class="rounded-lg border p-4">
            <h3 class="font-semibold">Affected Products *</h3>
            <p class="mt-1 text-xs text-slate-500">Select at least one product (maximum 30). Search by SAP code or
                product name.</p>
            <input type="search" wire:model.live.debounce.350ms="productSearch"
                class="mt-3 w-full rounded-lg border p-3" placeholder="Search SAP code / product...">
            <div class="mt-3 max-h-64 space-y-2 overflow-y-auto rounded border p-3">
                @forelse ($products as $product)
                    <label wire:key="product-option-{{ $product->id }}"
                        class="flex cursor-pointer items-center gap-3 text-sm">
                        <input type="checkbox" wire:model.live="selectedProductIds" value="{{ $product->id }}">
                        <span><strong>{{ $product->sap_code }}</strong> — {{ $product->product_name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-slate-500">No matching products.</p>
                @endforelse
            </div>
            <div class="mt-3 text-sm"><strong>Selected ({{ count($selectedProductIds) }}):</strong>
                @foreach ($selectedProducts as $product)
                    <span
                        class="mr-2 inline-block rounded bg-blue-50 px-2 py-1 text-blue-800">{{ $product->sap_code }}</span>
                @endforeach
            </div>
            @error('product_ids')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
            @error('product_ids.*')
            <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </section>
        <button type="submit" wire:loading.attr="disabled" wire:target="save"
            class="rounded-lg bg-blue-700 px-6 py-3 font-semibold text-white disabled:opacity-50">Save IAR
            Draft</button>
    </form>
</div>
