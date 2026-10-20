<div class="min-h-screen bg-slate-100 p-6">

    <div class="max-w-7xl mx-auto">

        <div class="mb-8">
            <a href="{{ route('dashboard') }}" class="text-blue-700">
                &larr; Back to Dashboard
            </a>

            <h1 class="text-3xl font-bold mt-4">
                Master Data Management
            </h1>

            <p class="text-slate-500 mt-2">
                Regulatory master database overview
            </p>
        </div>


        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            @foreach ([
                    'Products' => $stats['products'],
                    'Registrations' => $stats['registrations'],
                    'Countries' => $stats['countries'],
                    'Global RA Contacts' => $stats['global_ra'],
                    'Local RA Contacts' => $stats['local_ra'],
                    'Import Issues' => $stats['import_issues'],
                ] as $title => $count)

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <p class="text-slate-500">
                        {{ $title }}
                    </p>

                    <h2 class="text-3xl font-bold mt-3">
                        {{ number_format($count) }}
                    </h2>

                    @if ($title === 'Products')
                        <a href="{{ route('admin.products') }}"
                            class="inline-block mt-4 text-blue-700 font-semibold hover:underline">
                            Browse Products →
                        </a>
                    @endif
                </div>

            @endforeach
        </div>

        <div class="bg-white shadow-sm rounded-xl mt-8 p-6">

            <h2 class="text-xl font-bold mb-5">
                Import Issues Summary
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b bg-slate-50">
                        <tr>
                            <th class="p-3">Source</th>
                            <th class="p-3">Issue</th>
                            <th class="p-3">Count</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($issues as $issue)
                            <tr class="border-b">
                                <td class="p-3">
                                    {{ $issue->source_file }}
                                </td>

                                <td class="p-3">
                                    {{ $issue->issue_code }}
                                </td>

                                <td class="p-3">
                                    {{ $issue->total }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="p-4">
                                    No import issues recorded.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>
