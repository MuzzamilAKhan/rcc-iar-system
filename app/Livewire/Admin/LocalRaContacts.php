<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class LocalRaContacts extends Component
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

        $countries = DB::table('countries')
            ->leftJoin(
                'regions',
                'countries.region_id',
                '=',
                'regions.id'
            )
            ->select(
                'countries.id',
                'countries.name',
                'countries.iso2',
                'regions.name as region_name'
            )
            ->when($term !== '', function ($query) use ($term) {
                $search = "%{$term}%";

                $query->where(function ($q) use ($search) {
                    $q->where('countries.name', 'like', $search)
                        ->orWhere('countries.iso2', 'like', $search)
                        ->orWhere('regions.name', 'like', $search)
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->selectRaw('1')
                                ->from('country_assessors')
                                ->join(
                                    'users',
                                    'users.id',
                                    '=',
                                    'country_assessors.user_id'
                                )
                                ->whereColumn(
                                    'country_assessors.country_id',
                                    'countries.id'
                                )
                                ->where(function ($contact) use ($search) {
                                    $contact->where(
                                        'users.name',
                                        'like',
                                        $search
                                    )->orWhere(
                                            'users.email',
                                            'like',
                                            $search
                                        );
                                });
                        });
                });
            })
            ->orderBy('countries.name')
            ->paginate(15);

        // Fetch assessor mappings for the current page only.
        $assessors = DB::table('country_assessors')
            ->join(
                'users',
                'users.id',
                '=',
                'country_assessors.user_id'
            )
            ->whereIn(
                'country_assessors.country_id',
                $countries->getCollection()->pluck('id')
            )
            ->select(
                'country_assessors.country_id',
                'country_assessors.assignment_priority',
                'country_assessors.is_active as mapping_active',
                'users.name',
                'users.email',
                'users.is_active as account_active'
            )
            ->orderBy('country_assessors.assignment_priority')
            ->get()
            ->groupBy('country_id');

        return view('livewire.admin.local-ra-contacts', [
            'countries' => $countries,
            'assessors' => $assessors,
        ]);
    }
}
