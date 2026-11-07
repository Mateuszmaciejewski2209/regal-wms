<?php

namespace App\Livewire;

use App\Services\Scanner\ScannerService;
use Livewire\Component;

/**
 * Wskaźnik w pasku górnym: ile telefonów jest online i do jakiego dokumentu trafiają skany.
 */
class ScannerStatus extends Component
{
    public function getListeners(): array
    {
        $id = auth()->id();

        return [
            'scanner-target-changed' => '$refresh',
            'echo-private:warehouse,.document.changed' => '$refresh',
            "echo-private:scanner.{$id},.scan.received" => '$refresh',
            "echo-private:scanner.{$id},.scanner.paired" => '$refresh',
        ];
    }

    public function clearTarget(ScannerService $scanner): void
    {
        $scanner->setTarget(auth()->user(), null);
        $this->dispatch('scanner-target-changed');
    }

    public function render(ScannerService $scanner)
    {
        $user = auth()->user();

        return view('livewire.scanner-status', [
            'target' => $scanner->currentTarget($user),
            'online' => $user->scannerDevices()->active()->where('last_seen_at', '>=', now()->subMinutes(2))->count(),
            'paired' => $user->scannerDevices()->active()->count(),
        ]);
    }
}
