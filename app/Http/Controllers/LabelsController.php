<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Arkusz etykiet z kodami QR do wydruku (A4, siatka 3 × 8). */
class LabelsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $ids = collect($request->input('ids', []))->map(fn ($id) => (int) $id)->filter();
        $copies = max(1, min(24, $request->integer('copies', 1)));

        $products = Product::query()
            ->when($ids->isNotEmpty(), fn ($q) => $q->whereIn('id', $ids), fn ($q) => $q->active())
            ->orderBy('name')
            ->limit(240)
            ->get();

        $labels = $products->flatMap(fn (Product $p) => array_fill(0, $copies, $p));

        return view('labels', ['labels' => $labels]);
    }
}
