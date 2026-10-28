<div class="min-h-screen bg-slate-100 p-6">
    <main class="max-w-7xl mx-auto">

        <a href="{{ route('admin.master-data') }}" class="text-blue-700 hover:underline">
            &larr; Back to Master Data
        </a>

        <div class="mt-6 mb-8">
            <h1 class="text-3xl font-bold">
                Local RA Country Assessors
            </h1>
            <p class="text-slate-500 mt-2">
                Country-wise primary and backup assessor mappings
            </p>
        </div>

        @role('super-admin')
        <a href="{{ route('admin.primary-mapping.index') }}" class="ra-primary-mapping-action mb-5 inline-flex items-center justify-center rounded-lg px-4 py-2 text-sm font-semibold shadow-sm transition hover:opacity-90" style="background-color:#0f766e;color:#ffffff;text-decoration:none;border:1px solid #115e59;min-height:42px;">Manage primary assessor mappings</a>
        @endrole

        <div class="bg-white rounded-xl shadow-sm p-6">

            <input type="search" wire:model.live.debounce.400ms="search"
                placeholder="Search country, region, assessor or email..."
                class="w-full border border-slate-300 rounded-lg px-4 py-3 mb-6">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-3">Country</th>
                            <th class="p-3">Region</th>
                            <th class="p-3">Primary Assessor</th>
                            <th class="p-3">Backup Assessor</th>
                            <th class="p-3">Mapping Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($countries as $country)
                            @php
                                $contacts = $assessors->get(
                                    $country->id,
                                    collect()
                                );

                                $primary = $contacts->firstWhere(
                                    'assignment_priority',
                                    1
                                );

                                $backup = $contacts->firstWhere(
                                    'assignment_priority',
                                    2
                                );
                            @endphp

                            <tr wire:key="country-{{ $country->id }}" class="border-b align-top hover:bg-slate-50">

                                <td class="p-3 font-medium">
                                    {{ $country->name }}
                                    <div class="text-xs text-slate-400">
                                        {{ $country->iso2 }}
                                    </div>
                                </td>

                                <td class="p-3">
                                    {{ $country->region_name ?? 'N/A' }}
                                </td>

                                <td class="p-3">
                                    @if ($primary)
                                                            <div class="font-medium">
                                                                {{ $primary->name }}
                                                            </div>
                                                            <div class="text-xs text-slate-500">
                                                                {{ $primary->email }}
                                                            </div>
                                                            <div class="text-xs mt-1
                                                                        {{ $primary->account_active && $primary->mapping_active
                                        ? 'text-green-700'
                                        : 'text-amber-700' }}">
                                                                {{ $primary->account_active && $primary->mapping_active
                                        ? 'Active'
                                        : 'Inactive / Unavailable' }}
                                                            </div>
                                    @else
                                        <span class="text-red-600">
                                            Not Assigned
                                        </span>
                                    @endif
                                </td>

                                <td class="p-3">
                                    @if ($backup)
                                                            <div class="font-medium">
                                                                {{ $backup->name }}
                                                            </div>
                                                            <div class="text-xs text-slate-500">
                                                                {{ $backup->email }}
                                                            </div>
                                                            <div class="text-xs mt-1
                                                                        {{ $backup->account_active && $backup->mapping_active
                                        ? 'text-green-700'
                                        : 'text-amber-700' }}">
                                                                {{ $backup->account_active && $backup->mapping_active
                                        ? 'Active'
                                        : 'Inactive / Unavailable' }}
                                                            </div>
                                    @else
                                        <span class="text-slate-400">
                                            Not Assigned
                                        </span>
                                    @endif
                                </td>

                                <td class="p-3">
                                    @if ($primary && $primary->account_active && $primary->mapping_active)
                                        <span class="text-green-700">
                                            Available
                                        </span>
                                    @elseif ($primary)
                                        <span class="text-amber-700">
                                            Requires Activation
                                        </span>
                                    @else
                                        <span class="text-red-600">
                                            Missing Primary
                                        </span>
                                    @endif
                                </td>
                            </tr>

                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">
                                    No countries found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $countries->links() }}
            </div>
        </div>
    </main>
</div>
