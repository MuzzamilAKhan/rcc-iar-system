<?php

namespace App\Livewire\Admin;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class ImportIssuesIndex extends Component
{
    use WithPagination;

    public string $source = '';
    public string $issueCode = '';
    public string $search = '';

    public function updatedSource(): void
    {
        $this->resetPage();
    }

    public function updatedIssueCode(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $issues = DB::table('master_import_issues')
            ->when(
                $this->source !== '',
                fn($q) =>
                    $q->where('source_file', $this->source)
            )
            ->when(
                $this->issueCode !== '',
                fn($q) =>
                    $q->where('issue_code', $this->issueCode)
            )
            ->when(trim($this->search) !== '', function ($q) {
                $term = '%' . trim($this->search) . '%';

                $q->where(function ($query) use ($term) {
                    $query->where('message', 'like', $term)
                        ->orWhere('issue_code', 'like', $term);
                });
            })
            ->orderBy('source_file')
            ->orderBy('source_row')
            ->paginate(20);

        return view('livewire.admin.import-issues-index', [
            'issues' => $issues,
            'sources' => DB::table('master_import_issues')
                ->distinct()
                ->orderBy('source_file')
                ->pluck('source_file'),

            'issueTypes' => DB::table('master_import_issues')
                ->distinct()
                ->orderBy('issue_code')
                ->pluck('issue_code'),
        ]);
    }
}
