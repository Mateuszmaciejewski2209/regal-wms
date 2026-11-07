<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\Layout\RackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

/** Wydruk dokumentu magazynowego do PDF. */
class DocumentPdfController extends Controller
{
    public function __invoke(Document $document): Response
    {
        $document->load(['items.product', 'warehouse', 'targetWarehouse', 'contractor', 'author', 'approver', 'parent', 'children']);

        $filename = str_replace('/', '-', $document->displayNumber()).'.pdf';

        // Lokalizacje na wydruku: magazynier wie, gdzie odłożyć (przychód) lub skąd pobrać (rozchód).
        $locations = app(RackService::class)->codesFor($document->items->pluck('product_id'), $document->warehouse_id);

        return Pdf::loadView('pdf.document', ['document' => $document, 'locations' => $locations])
            ->setPaper('a4')
            ->stream($filename);
    }
}
