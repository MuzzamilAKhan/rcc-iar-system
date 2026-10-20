<div class="min-h-screen bg-slate-100 p-6">
    <main class="max-w-7xl mx-auto">

        <a href="{{ route('admin.master-data') }}" class="text-blue-700">
            &larr; Back to Master Data
        </a>

        <div class="mt-6 mb-8">
            <h1 class="text-3xl font-bold">
                Account Provisioning
            </h1>
            <p class="text-slate-500 mt-2">
                Review imported regulatory user accounts
            </p>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6">

            <div class="grid md:grid-cols-2 gap-4 mb-6">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search name or email..."
                    class="border rounded-lg px-4 py-3">

                <select wire:model.live="status" class="border rounded-lg px-4 py-3">
                    <option value="">All Provisioning Statuses</option>
                    @foreach ([
                            'pending_review',
                            'approved',
                            'invited',
                            'active',
                            'rejected',
                            'suspended'
                        ] as $option)
                        <option value="{{ $option }}">
                            {{ ucwords(str_replace('_', ' ', $option)) }}
                        </option>
                    @endforeach
                </select>
            </div>


            @if (session()->has('success'))
                <div class="mb-5 rounded-lg bg-green-50 p-4 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($selectedUserId !== null)
                <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
                    <h2 class="text-lg font-bold">
                        Confirm Account Approval
                    </h2>

                    <p class="mt-2 text-sm text-slate-700">
                        You are approving this contact for a future secure
                        invitation. This action will not activate login access
                        or send an email.
                    </p>

                    <label class="mt-4 block text-sm font-semibold">
                        Approval Reason
                    </label>

                    <textarea wire:model="approvalReason" rows="3" maxlength="1000"
                        class="mt-2 w-full rounded-lg border border-slate-300 p-3"
                        placeholder="Explain how the identity and role were verified..."></textarea>

                    @error('approvalReason')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('selectedUserId')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror

                    @error('user')
                        <p class="mt-2 text-sm text-red-700">
                            {{ $message }}
                        </p>
                    @enderror

                    <div class="mt-4 flex gap-3">
                        <button type="button" wire:click="approveSelected" wire:loading.attr="disabled"
                            wire:target="approveSelected"
                            class="rounded-lg bg-blue-700 px-5 py-2 text-white disabled:opacity-50">
                            Confirm Approval
                        </button>

                        <button type="button" wire:click="cancelApproval"
                            class="rounded-lg border border-slate-300 bg-white px-5 py-2">
                            Cancel
                        </button>
                    </div>
                </div>
            @endif


            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Roles</th>
                            <th class="p-3">Provisioning</th>
                            <th class="p-3">Login</th>
                            <th class="p-3">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($users as $user)
                            <tr wire:key="user-{{ $user->id }}" class="border-b">
                                <td class="p-3 font-medium">
                                    {{ $user->name }}
                                </td>
                                <td class="p-3">
                                    {{ $user->email }}
                                </td>
                                <td class="p-3">
                                    {{ $user->roles->pluck('name')->implode(', ') ?: 'No Role' }}
                                </td>
                                <td class="p-3">
                                    {{ ucwords(str_replace('_', ' ', $user->provisioning_status)) }}
                                </td>
                                <td class="p-3">
                                    @if ($user->is_active)
                                        <span class="text-green-700">Enabled</span>
                                    @else
                                        <span class="text-amber-700">Disabled</span>
                                    @endif
                                </td>

                                <td class="p-3">
                                    @if (
                                            !$user->is_active &&
                                            $user->provisioning_status === 'pending_review' &&
                                            $user->hasAnyRole(['global-ra', 'local-ra'])
                                        )
                                        <button type="button" wire:click="selectForApproval({{ $user->id }})"
                                            class="text-blue-700 font-semibold hover:underline">
                                            Review
                                        </button>
                                    @else
                                        <span class="text-slate-400">
                                            —
                                        </span>
                                    @endif
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-500">
                                    No user accounts found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-6">
                {{ $users->links() }}
            </div>
        </div>
    </main>
</div>
