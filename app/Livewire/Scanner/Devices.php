<?php

namespace App\Livewire\Scanner;

use App\Models\ScanEvent;
use App\Services\Scanner\ScannerService;
use App\Support\Qr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Skanery mobilne')]
class Devices extends Component
{
    public bool $showPairing = false;

    public ?string $pairingCode = null;

    public ?string $pairingUrl = null;

    public ?string $expiresAt = null;

    /** Liczba sparowanych urządzeń w chwili otwarcia okna – wzrost oznacza udane parowanie. */
    public int $devicesAtStart = 0;

    public function getListeners(): array
    {
        $id = auth()->id();

        return [
            "echo-private:scanner.{$id},.scanner.paired" => 'onPaired',
            "echo-private:scanner.{$id},.scan.received" => '$refresh',
        ];
    }

    public function startPairing(ScannerService $scanner): void
    {
        $this->pairingCode = $scanner->createPairingCode(auth()->user());
        $this->pairingUrl = rtrim($this->publicBaseUrl(), '/').'/skaner#pair='.$this->pairingCode;
        $this->expiresAt = now()->addMinutes(ScannerService::PAIRING_TTL_MINUTES)->toIso8601String();
        $this->devicesAtStart = auth()->user()->scannerDevices()->active()->count();
        $this->showPairing = true;
    }

    /** Zapasowe sprawdzenie (gdy WebSocket nie działa): czy przybyło urządzenie. */
    public function checkPaired(): void
    {
        if ($this->showPairing && auth()->user()->scannerDevices()->active()->count() > $this->devicesAtStart) {
            $this->onPaired([]);
            $this->dispatch('toast', title: 'Sparowano telefon', message: 'Możesz skanować.', tone: 'success');
        }
    }

    public function onPaired(array $device): void
    {
        $this->showPairing = false;
        $this->pairingCode = null;
        $this->pairingUrl = null;
    }

    public function revoke(int $id): void
    {
        $device = auth()->user()->scannerDevices()->findOrFail($id);
        $device->forceFill(['revoked_at' => now()])->save();

        $this->dispatch('toast', title: 'Odłączono urządzenie', message: $device->name, tone: 'neutral');
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.scanner.devices', [
            'devices' => $user->scannerDevices()->active()->latest('last_seen_at')->get(),
            'scans' => ScanEvent::query()
                ->where('user_id', $user->id)
                ->with(['product:id,name', 'document:id,type,number', 'device:id,name'])
                ->latest('id')
                ->limit(25)
                ->get(),
            'qr' => $this->pairingUrl ? Qr::svg($this->pairingUrl, 220) : null,
            'expiresIn' => $this->expiresAt ? (int) now()->diffInSeconds($this->expiresAt, false) : 0,
            'baseUrl' => $this->publicBaseUrl(),
            'isSecure' => str_starts_with($this->publicBaseUrl(), 'https://'),
        ]);
    }

    /**
     * Adres, pod którym telefon zobaczy aplikację:
     * 1) SCANNER_PUBLIC_URL, 2) adres tunelu Cloudflare z Dockera, 3) host z żądania (o ile nie localhost),
     * 4) IP w sieci LAN (tylko poza kontenerem – w Dockerze to byłby adres sieci wewnętrznej).
     */
    private function publicBaseUrl(): string
    {
        if ($configured = config('regal.scanner_url')) {
            return rtrim($configured, '/');
        }

        if ($tunnel = $this->tunnelUrl()) {
            return $tunnel;
        }

        $request = request();
        $host = $request->getHost();

        if (! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.localhost')) {
            return $request->getSchemeAndHttpHost();
        }

        if (file_exists('/.dockerenv')) {
            return $request->getSchemeAndHttpHost();
        }

        $ips = gethostbynamel(gethostname()) ?: [];
        $lan = collect($ips)->first(fn ($ip) => str_starts_with($ip, '192.168.'))
            ?? collect($ips)->first(fn ($ip) => str_starts_with($ip, '10.') || preg_match('/^172\.(1[6-9]|2\d|3[01])\./', $ip));

        $port = $request->getPort();

        return $lan
            ? 'http://'.$lan.(in_array($port, [80, 443], true) ? '' : ':'.$port)
            : $request->getSchemeAndHttpHost();
    }

    /** Szybki tunel cloudflared publikuje swoją nazwę hosta w endpointcie metryk (/quicktunnel). */
    private function tunnelUrl(): ?string
    {
        $endpoint = config('regal.tunnel_metrics_url');

        if (! $endpoint) {
            return null;
        }

        return Cache::remember('regal.tunnel-url', 30, function () use ($endpoint) {
            try {
                $hostname = Http::timeout(1)->get($endpoint)->json('hostname');
            } catch (\Throwable) {
                return null;
            }

            return $hostname ? 'https://'.$hostname : null;
        });
    }
}
