<?php

namespace App\Http\Controllers;

use App\Models\ProductLocation;
use App\Models\Rack;
use Illuminate\View\View;

/** Etykiety QR miejsc składowania (każde pole każdej półki regału) – skan telefonem pokazuje zawartość półki. */
class LocationLabelsController extends Controller
{
    public function __invoke(Rack $rack): View
    {
        $rack->load('warehouse');
        $labels = [];

        for ($level = $rack->levels; $level >= 1; $level--) {
            for ($bay = 1; $bay <= $rack->bays; $bay++) {
                $labels[] = [
                    'code' => ProductLocation::code($rack, $level, $bay),
                    'label' => ProductLocation::label($rack, $level, $bay),
                    'qr' => ProductLocation::qrPayload($rack, $level, $bay),
                ];
            }
        }

        return view('labels-locations', ['rack' => $rack, 'labels' => $labels]);
    }
}
