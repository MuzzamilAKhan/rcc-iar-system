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
        <section class="rounded-lg border border-blue-200 bg-blue-50 p-4">
            <h3 class="font-semibold">Bulk SAP code validation (Excel paste)</h3>
            <p class="mt-1 text-sm text-slate-600">Copy a column of SAP codes from Excel, paste below, and preview matches, duplicates and missing codes. Validation shows issues without database changes. On Save, pasted codes take priority over checkboxes; missing SAP codes can be retained in drafts only with an override justification. Ambiguous SAP matches remain blocked. Duplicate lines are deduplicated.</p>
                        <div class="mt-3 rounded border border-blue-200 bg-white p-3">
                <label class="block text-sm font-semibold">Upload SAP codes from Excel (.xlsx or .csv; maximum 4 MB)</label>
                <p class="mt-1 text-xs text-slate-600">Use a single SAP-code column, optionally headed SAP Code; or a multi-column file with a SAP Code header. First worksheet only. Maximum 10,000 rows. Uploaded codes replace the pasted list below, not the Product Registry.</p>
                <div x-data="{ fileName: '' }" class="mt-3">
                    <label class="relative flex min-h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-blue-300 bg-blue-50 px-4 py-5 text-center transition hover:border-blue-600 hover:bg-blue-100 focus-within:ring-2 focus-within:ring-blue-600">
                        <span class="inline-flex items-center justify-center rounded-lg bg-blue-700 px-5 py-3 text-sm font-bold text-white shadow-sm">
                            Choose Excel / CSV File
                        </span>
                        <span class="text-xs text-slate-600">Click here to browse your computer (.xlsx or .csv, max 4 MB)</span>
                        <input type="file" wire:model="sapExcelFile" accept=".xlsx,.csv" aria-label="Choose Excel or CSV SAP code file" class="absolute inset-0 h-full w-full cursor-pointer opacity-0" x-on:change="fileName = $event.target.files?.[0]?.name || ''">
                    </label>
                    <p class="mt-2 break-all text-sm" aria-live="polite" x-text="fileName ? 'Selected file: ' + fileName : 'No file selected yet.'"></p>
                </div>
                @error('sapExcelFile')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                <button type="button" wire:click="importSapExcel" wire:loading.attr="disabled" wire:target="sapExcelFile,importSapExcel" class="mt-2 rounded bg-blue-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Read uploaded SAP codes</button>
                <span wire:loading wire:target="sapExcelFile,importSapExcel" class="ml-2 text-sm">Processing upload...</span>
            </div>
            <textarea wire:model="bulkSapInput" rows="7" class="mt-3 w-full rounded border p-3 font-mono text-sm" placeholder="00012345 (one code per line)"></textarea>
            @error('bulkSapInput') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <button type="button" wire:click="previewBulkSap" wire:loading.attr="disabled" wire:target="previewBulkSap" class="mt-2 rounded bg-blue-700 px-4 py-2 font-semibold text-white">Validate pasted SAP codes</button>
            @if ($bulkSapReport !== null)
                <div class="mt-4 rounded bg-white p-3 text-sm">
                    <p><strong>{{ $bulkSapReport['total_entries'] }}</strong> entries; <strong>{{ $bulkSapReport['matched_count'] }}</strong> matched; <strong>{{ $bulkSapReport['missing_count'] }}</strong> missing; <strong>{{ $bulkSapReport['ambiguous_count'] }}</strong> ambiguous; <strong>{{ $bulkSapReport['duplicate_count'] }}</strong> duplicates.</p>
                    @if ($bulkSapReport['missing_preview'])<p class="mt-2 text-amber-800"><strong>Missing (first 100):</strong> {{ implode(', ', $bulkSapReport['missing_preview']) }}</p>@endif
                    @if ($bulkSapReport['ambiguous_preview'])<p class="mt-2 text-amber-800"><strong>Ambiguous (first 100):</strong> {{ implode(', ', $bulkSapReport['ambiguous_preview']) }}</p>@endif
                    @if ($bulkSapReport['duplicate_preview'])<p class="mt-2 text-amber-800"><strong>Duplicates (first 100):</strong> {{ implode(', ', $bulkSapReport['duplicate_preview']) }}</p>@endif
                    @if ($bulkSapReport['matched_preview'])<p class="mt-2 text-green-800"><strong>Matched examples:</strong> {{ implode(', ', array_column($bulkSapReport['matched_preview'], 'code')) }}</p>@endif
                </div>
            @endif
            <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
                <label class="flex items-start gap-2 font-semibold text-amber-900">
                    <input type="checkbox" wire:model="overrideWarnings" class="mt-1">
                    Retain missing SAP codes as unresolved draft items (override warning)
                </label>
                <p class="mt-2 text-amber-900">Only missing SAP codes can be overridden. Ambiguous product matches remain blocked. An override does not authorize IAR submission or country assignment.</p>
                <label class="mt-3 block font-semibold text-amber-900">Reason for override (mandatory when missing codes exist)</label>
                <textarea wire:model="overrideReason" rows="3" maxlength="2000" class="mt-2 w-full rounded border border-amber-300 bg-white p-3" placeholder="Explain why the unresolved codes must be retained in this draft..."></textarea>
                @error('overrideWarnings')<p class="mt-1 text-red-700">{{ $message }}</p>@enderror
                @error('overrideReason')<p class="mt-1 text-red-700">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="rounded-lg border p-4">
            <h3 class="font-semibold">Affected Products *</h3>
            <p class="mt-1 text-xs text-slate-500">Alternatively select products manually. When SAP codes are pasted above, the pasted list replaces checkbox selections. Search by SAP code or
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
