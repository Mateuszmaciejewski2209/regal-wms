<?php

namespace App\Services\Documents;

use App\Enums\DocumentType;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Ciągła numeracja dokumentów w obrębie typu i miesiąca: PZ/2026/11/0001.
 * Licznik jest blokowany w transakcji, więc dwa równoległe zatwierdzenia nie dostaną tego samego numeru.
 */
class DocumentNumberGenerator
{
    public function next(DocumentType $type, CarbonInterface $date): string
    {
        $key = ['type' => $type->value, 'year' => $date->year, 'month' => $date->month];

        DB::table('document_sequences')->insertOrIgnore($key + ['last_number' => 0]);

        $row = DB::table('document_sequences')->where($key)->lockForUpdate()->first();
        $number = $row->last_number + 1;

        DB::table('document_sequences')->where('id', $row->id)->update(['last_number' => $number]);

        return sprintf('%s/%04d/%02d/%04d', $type->value, $date->year, $date->month, $number);
    }
}
