<?php

namespace App\Livewire\Iar;

use App\Models\ImpactAssessment;
use Livewire\Component;
use Livewire\WithPagination;

class IarIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }

    public function render()
    {
        $actor = auth()->user();
        abort_unless($actor?->is_active && ($actor->can('iar.view.own') || $actor->can('iar.view.all') || $actor->can('iar.view.assigned')), 403);

        $iars = ImpactAssessment::query()
            ->with(['creator'])
            ->withCount('products')
            ->when(!$actor->can('iar.view.all'), function ($query) use ($actor) {
                $query->where(function ($inner) use ($actor) {
                    if ($actor->can('iar.view.own')) {
                        $inner->where('creator_id', $actor->id);
                    }
                    if ($actor->can('iar.view.assigned')) {
                        $inner->orWhereHas('assignments', fn ($assignment) => $assignment->where('assigned_to_user_id', $actor->id));
                    }
                });
            })
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';
                $q->where(fn ($inner) => $inner->where('iar_number', 'like', $term)->orWhere('ccr_number', 'like', $term));
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.iar.iar-index', compact('iars'));
    }
}
