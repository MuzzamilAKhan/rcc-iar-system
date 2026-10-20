<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use App\Services\Provisioning\ApproveImportedUser;

class UserProvisioning extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public ?int $selectedUserId = null;
    public string $approvalReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';

                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when(
                $this->status !== '',
                fn($q) =>
                    $q->where('provisioning_status', $this->status)
            )
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.user-provisioning', [
            'users' => $users,
        ]);
    }


    public function selectForApproval(int $userId): void
    {
        abort_unless(
            auth()->user()?->is_active &&
            auth()->user()?->hasRole('super-admin'),
            403
        );

        $user = User::findOrFail($userId);

        if (
            $user->is_active ||
            $user->provisioning_status !== 'pending_review' ||
            !$user->hasAnyRole(['global-ra', 'local-ra'])
        ) {
            $this->addError(
                'approvalReason',
                'This account is not eligible for approval.'
            );

            return;
        }

        $this->resetValidation();

        $this->selectedUserId = $user->id;
        $this->approvalReason = '';
    }

    public function cancelApproval(): void
    {
        $this->selectedUserId = null;
        $this->approvalReason = '';
        $this->resetValidation();
    }

    public function approveSelected(
        ApproveImportedUser $service
    ): void {
        abort_unless(
            auth()->user()?->is_active &&
            auth()->user()?->hasRole('super-admin'),
            403
        );

        $this->validate([
            'selectedUserId' => ['required', 'integer'],
            'approvalReason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $service->approve(
            auth()->user(),
            $this->selectedUserId,
            $this->approvalReason
        );

        $this->cancelApproval();

        session()->flash(
            'success',
            'Contact approved. Account remains inactive.'
        );
    }

}
