<div class="min-h-screen bg-slate-100 p-6">
    <main class="max-w-7xl mx-auto">

        <a href="{{ route('admin.master-data') }}" class="text-blue-700 hover:underline">
            &larr; Back to Master Data
        </a>

        <div class="mt-6 mb-8">
            <h1 class="text-3xl font-bold">Global RA Contacts</h1>
            <p class="text-slate-500 mt-2">
                Global Regulatory Affairs contacts and business units
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">

            <input type="search" wire:model.live.debounce.400ms="search"
                placeholder="Search name, email, position or BU..."
                class="w-full border border-slate-300 rounded-lg px-4 py-3 mb-6">

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Position</th>
                            <th class="p-3">Business Units</th>
                            <th class="p-3">Account Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr wire:key="global-ra-{{ $contact->id }}" class="border-b hover:bg-slate-50">
                                <td class="p-3 font-medium">
                                    {{ $contact->name }}
                                </td>
                                <td class="p-3">
                                    {{ $contact->email }}
                                </td>
                                <td class="p-3">
                                    {{ $contact->position ?? 'N/A' }}
                                </td>
                                <td class="p-3">
                                    {{ $contact->businessUnits->pluck('code')->sort()->implode(', ') ?: 'N/A' }}
                                </td>
                                <td class="p-3">
                                    @if ($contact->is_active)
                                        <span class="text-green-700">Active</span>
                                    @else
                                        <span class="text-amber-700">Inactive</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">
                                    No contacts found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $contacts->links() }}
            </div>
        </div>
    </main>
</div>
