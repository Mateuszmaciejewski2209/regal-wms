<?php

namespace App\Services\Documents;

use App\Models\Document;

/**
 * Reprezentacja dokumentu przekazywana w webhookach (kontrakt integracyjny).
 */
final class DocumentPayload
{
    public static function make(Document $document): array
    {
        $document->loadMissing(['items.product', 'warehouse', 'targetWarehouse', 'contractor', 'approver']);

        return [
            'id' => $document->id,
            'type' => $document->type->value,
            'type_label' => $document->type->label(),
            'number' => $document->number,
            'status' => $document->status->value,
            'issued_at' => $document->issued_at?->toDateString(),
            'warehouse' => $document->warehouse?->code,
            'target_warehouse' => $document->targetWarehouse?->code,
            'contractor' => $document->contractor ? [
                'name' => $document->contractor->name,
                'nip' => $document->contractor->nip,
            ] : null,
            'external_number' => $document->external_number,
            'total_value' => (string) $document->total_value,
            'approved_at' => $document->approved_at?->toIso8601String(),
            'approved_by' => $document->approver?->name,
            'cancelled_at' => $document->cancelled_at?->toIso8601String(),
            'parent_id' => $document->parent_id,
            'items' => $document->items->map(fn ($item) => [
                'sku' => $item->product->sku,
                'ean' => $item->product->ean,
                'name' => $item->product->name,
                'quantity' => $item->quantity !== null ? (string) $item->quantity : null,
                'expected_quantity' => $item->expected_quantity !== null ? (string) $item->expected_quantity : null,
                'unit' => $item->product->unit->value,
                'unit_price' => $item->unit_price !== null ? (string) $item->unit_price : null,
            ])->values()->all(),
        ];
    }
}
