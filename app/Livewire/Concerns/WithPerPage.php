<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Paginacja z wyborem liczby rekordów na stronie (zapamiętywaną w adresie URL).
 */
trait WithPerPage
{
    use WithPagination;

    #[Url(as: 'ile', except: 15)]
    public int $perPage = 15;

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    protected function perPage(): int
    {
        return in_array($this->perPage, [10, 15, 25, 50, 100], true) ? $this->perPage : 15;
    }
}
