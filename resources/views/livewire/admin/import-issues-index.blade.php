<div class="min-h-screen bg-slate-100 p-6">
    <main class="max-w-7xl mx-auto">

        @can('manage-master-data')
            <a href="{{ route('admin.master-data') }}" class="text-blue-700 hover:underline">
                &larr; Back to Master Data
            </a>
        @else
            <a href="{{ route('dashboard') }}" class="text-blue-700 hover:underline">
                &larr; Back to Dashboard
            </a>
        @endcan

        <div class="mt-6 mb-8">
            <h1 class="text-3xl font-bold">
                Import Issues
            </h1>
            <p class="text-slate-500 mt-2">
                Review client master data import exceptions
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="grid md:grid-cols-3 gap-4 mb-6">

                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search issue or message..."
                    class="border rounded-lg px-4 py-3">

                <select wire:model.live="source" class="border rounded-lg px-4 py-3">
                    <option value="">All Sources</option>
                    @foreach ($sources as $file)
                        <option value="{{ $file }}">
                            {{ $file }}
                        </option>
                    @endforeach
                </select>

                <select wire:model.live="issueCode" class="border rounded-lg px-4 py-3">
                    <option value="">All Issue Types</option>
                    @foreach ($issueTypes as $type)
                        <option value="{{ $type }}">
                            {{ $type }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-3">Source</th>
                            <th class="p-3">Row</th>
                            <th class="p-3">Issue Type</th>
                            <th class="p-3">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($issues as $issue)
                            <tr wire:key="issue-{{ $issue->id }}" class="border-b align-top">
                                <td class="p-3">
                                    {{ $issue->source_file }}
                                </td>
                                <td class="p-3">
                                    {{ $issue->source_row }}
                                </td>
                                <td class="p-3">
                                    {{ str_replace('_', ' ', $issue->issue_code) }}
                                </td>
                                <td class="p-3">
                                    {{ $issue->message }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500">
                                    No issues found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $issues->links() }}
            </div>
        </div>
    </main>
</div>
