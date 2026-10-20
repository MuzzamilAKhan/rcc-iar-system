<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class GlobalRaContacts extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $term = trim($this->search);

        $contacts = User::query()
            ->role('global-ra')
            ->with(['businessUnits'])
            ->when($term !== '', function (Builder $query) use ($term) {
                $query->where(function (Builder $q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('position', 'like', "%{$term}%")
                        ->orWhereHas('businessUnits', function (Builder $buQuery) use ($term) {
                            $buQuery->where(
                                'code',
                                'like',
                                "%{$term}%"
                            );
                        });
                });
            })
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.admin.global-ra-contacts', [
            'contacts' => $contacts,
        ]);
    }
}
