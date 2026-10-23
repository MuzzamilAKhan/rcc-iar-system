<?php

namespace App\Services\Iar;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ResolveBulkIarProducts
{
    /** Resolve fresh on save; never trust a previously rendered preview. */
    public function resolve(string $input): Collection
    {
        $codes = preg_split('/[\r\n\t,;]+/u', $input) ?: [];
        $codes = array_values(array_filter(array_map('trim', $codes), fn ($code) => $code !== ''));
        if (count($codes) < 1 || count($codes) > 10000) {
            throw ValidationException::withMessages(['bulkSapInput' => 'Paste between 1 and 10,000 SAP code entries.']);
        }
        foreach ($codes as $code) {
            if (mb_strlen($code) > 100) {
                throw ValidationException::withMessages(['bulkSapInput' => 'One or more SAP codes exceed 100 characters.']);
            }
        }

        $uniqueCodes = array_values(array_unique($codes));
        $found = collect();
        foreach (array_chunk($uniqueCodes, 400) as $chunk) {
            $found = $found->concat(Product::query()->whereIn('sap_code', $chunk)->get(['id', 'sap_code']));
        }
        $byCode = $found->groupBy('sap_code');
        $missing = [];
        $ambiguous = [];
        $products = collect();
        foreach ($uniqueCodes as $code) {
            $matches = $byCode->get($code, collect());
            if ($matches->isEmpty()) {
                $missing[] = $code;
            } elseif ($matches->count() !== 1) {
                $ambiguous[] = $code;
            } else {
                $products->push($matches->first());
            }
        }
        if ($missing || $ambiguous) {
            $parts = [];
            if ($missing) $parts[] = count($missing) . ' missing (e.g. ' . implode(', ', array_slice($missing, 0, 5)) . ')';
            if ($ambiguous) $parts[] = count($ambiguous) . ' ambiguous (e.g. ' . implode(', ', array_slice($ambiguous, 0, 5)) . ')';
            throw ValidationException::withMessages(['bulkSapInput' => 'Bulk SAP list cannot be saved: ' . implode('; ', $parts) . '. Resolve these codes first.']);
        }
        return $products;
    }
}
