<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\Provisioning\ApproveImportedUser;
use App\Services\Provisioning\VerifyImportedUserSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class UserProvisioning extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';
    public ?int $selectedUserId = null;
    public string $approvalReason = '';
    public ?int $selectedSourceLinkId = null;
    public string $verificationReason = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_active && auth()->user()?->hasRole('super-admin'), 403);
    }

    public function render()
    {
        $this->authorizeAdmin();

        $users = User::query()
            ->with('roles')
            ->when($this->search !== '', function ($query) {
                $term = '%' . trim($this->search) . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)->orWhere('email', 'like', $term);
                });
            })
            ->when($this->status !== '', fn($q) => $q->where('provisioning_status', $this->status))
            ->orderBy('name')
            ->paginate(15);

        $sources = collect();
        $reviewedUser = null;
        if ($this->selectedUserId !== null) {
            $reviewedUser = User::find($this->selectedUserId);
            if ($reviewedUser) {
                $sources = DB::table('imported_user_sources as links')
                    ->join('master_import_rows as rows', 'rows.id', '=', 'links.master_import_row_id')
                    ->where('links.user_id', $reviewedUser->id)
                    ->select(
                        'links.id',
                        'links.source_email',
                        'links.verification_status',
                        'links.verified_at',
                        'rows.source_file',
                        'rows.source_row',
                        'rows.record_kind'
                    )
                    ->orderBy('rows.source_file')->orderBy('rows.source_row')->get();
            }
        }

        $approvalReady = $sources->isNotEmpty()
            && $sources->every(fn($source) => $source->verification_status === 'verified'
                && strtolower(trim($source->source_email)) === strtolower(trim($reviewedUser->email))
                && in_array($source->record_kind, ['global_ra', 'local_ra'], true));

        return view('livewire.admin.user-provisioning', compact('users', 'sources', 'reviewedUser', 'approvalReady'));
    }

    public function selectForApproval(int $userId): void
    {
        $this->authorizeAdmin();
        $user = User::findOrFail($userId);
        if (
            $user->is_active || $user->provisioning_status !== 'pending_review'
            || !$user->hasAnyRole(['global-ra', 'local-ra'])
        ) {
            $this->addError('user', 'This account is not eligible for approval.');
            return;
        }

        $this->resetValidation();
        $this->selectedUserId = $user->id;
        $this->approvalReason = '';
        $this->selectedSourceLinkId = null;
        $this->verificationReason = '';
    }

    public function selectForVerification(int $linkId): void
    {
        $this->authorizeAdmin();
        if (
            $this->selectedUserId === null || !DB::table('imported_user_sources')
                ->where('id', $linkId)->where('user_id', $this->selectedUserId)
                ->where('verification_status', 'pending')->exists()
        ) {
            throw ValidationException::withMessages(['source' => 'Source is not eligible for verification.']);
        }
        $this->resetValidation();
        $this->selectedSourceLinkId = $linkId;
        $this->verificationReason = '';
    }

    public function verifySelectedSource(VerifyImportedUserSource $service): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'selectedUserId' => ['required', 'integer'],
            'selectedSourceLinkId' => ['required', 'integer'],
            'verificationReason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        // Livewire properties are browser-controlled; verify the selected link belongs to the reviewed user.
        if (
            !DB::table('imported_user_sources')->where('id', $this->selectedSourceLinkId)
                ->where('user_id', $this->selectedUserId)->exists()
        ) {
            throw ValidationException::withMessages(['source' => 'Source does not belong to this account.']);
        }

        try {
            $service->verify(auth()->user(), $this->selectedSourceLinkId, $this->verificationReason);
        } catch (ValidationException $e) {
            throw ValidationException::withMessages([
                'source' => collect($e->errors())->flatten()->first() ?? 'Verification failed.',
            ]);
        }

        $this->selectedSourceLinkId = null;
        $this->verificationReason = '';
        $this->resetValidation();
        session()->flash('success', 'Source verified. Account remains inactive.');
    }

    public function cancelVerification(): void
    {
        $this->selectedSourceLinkId = null;
        $this->verificationReason = '';
        $this->resetValidation();
    }

    public function cancelApproval(): void
    {
        $this->selectedUserId = null;
        $this->approvalReason = '';
        $this->cancelVerification();
    }

    public function approveSelected(ApproveImportedUser $service): void
    {
        $this->authorizeAdmin();
        $this->validate([
            'selectedUserId' => ['required', 'integer'],
            'approvalReason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        $service->approve(auth()->user(), $this->selectedUserId, $this->approvalReason);
        $this->cancelApproval();
        session()->flash('success', 'Contact approved. Account remains inactive.');
    }
}
