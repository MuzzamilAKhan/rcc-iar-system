<div class="min-h-screen bg-slate-100 p-6">
    <div class="max-w-7xl mx-auto">

        <a href="{{ route('admin.master-data') }}" class="text-blue-700">
            &larr; Back to Master Data
        </a>

        <div class="mt-6 mb-8">
            <h1 class="text-3xl font-bold">Product Registry</h1>
            <p class="text-slate-500 mt-2">
                Regulatory product master records
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">

            <div class="grid md:grid-cols-2 gap-4 mb-6">

                <input type="search" wire:model.live.debounce.400ms="search"
                    placeholder="Search SAP code, product or brand..."
                    class="w-full border border-slate-300 rounded-lg px-4 py-3">

                <select wire:model.live="businessUnit" class="w-full border border-slate-300 rounded-lg px-4 py-3">
                    <option value="">All Business Units</option>

                    @foreach ($businessUnits as $unit)
                        <option value="{{ $unit->id }}">
                            {{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-5 flex flex-wrap items-center gap-3">
                <button type="button" wire:click="exportRegistry('all')" class="rounded-lg bg-blue-700 px-4 py-2 text-sm font-semibold text-white">Export Full Registry (.xlsx)</button>
                <button type="button" wire:click="exportRegistry('filtered')" class="rounded-lg border border-blue-700 px-4 py-2 text-sm font-semibold text-blue-700">Export Filtered Results (.xlsx)</button>
                <p class="text-xs text-slate-500">Export uses one row per country registration. Products with no registration are flagged. Full export ignores filters.</p>
            </div>

            <div class="mb-5">
                <a href="{{ route('admin.products.import.preview') }}" class="inline-block rounded border border-amber-500 px-4 py-2 font-semibold text-amber-900">Preview Registry Import (.xlsx / .csv)</a>
                <p class="mt-1 text-xs text-amber-800">Preview only. No changes are applied to master data.</p>
            </div>

            <div class="mt-3"><a class="text-blue-700 underline" href="{{ route('admin.products.import.requests') }}">View Registry Import Approval Queue &rarr;</a></div>
<div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-3">SAP Code</th>
                            <th class="p-3">Product Name</th>
                            <th class="p-3">Brand</th>
                            <th class="p-3">Business Unit</th>
                            <th class="p-3">Registrations</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($products as $product)
                            <tr wire:key="product-{{ $product->id }}" class="border-b hover:bg-slate-50">

                                <td class="p-3 font-medium">
                                    <a href="{{ route('admin.products.show', $product) }}"
                                        class="text-blue-700 hover:underline">
                                        {{ $product->sap_code }}
                                    </a>
                                </td>

                                <td class="p-3">
                                    {{ $product->product_name }}
                                </td>

                                <td class="p-3">
                                    {{ $product->brand ?? 'N/A' }}
                                </td>

                                <td class="p-3">
                                    {{ $product->businessUnit?->name ?? 'N/A' }}
                                </td>

                                <td class="p-3">
                                    {{ $product->registrations_count }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">
                                    No products found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $products->links() }}
            </div>
        </div>
    </div>
</div>
