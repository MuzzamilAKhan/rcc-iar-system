<?php

namespace App\Services\Iar;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

final class PreviewBulkSapCodes
{
    public function inspect(string $input): array
    {
        // Excel column paste produces newline-separated values. CSV / tab-separated
        // input is accepted as a convenience. Never interpret SAP codes as numbers.
        $tokens = preg_split('/[\r\n\t,;]+/u', $input) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), static fn ($v) => $v !== ''));
        if (count($tokens) > 10000) {
            throw ValidationException::withMessages(['bulkSapInput' => 'Maximum 10,000 pasted entries per preview. Split larger lists into batches.']);
        }

        $unique = [];
        $duplicates = [];
        foreach ($tokens as $code) {
            if (mb_strlen($code) > 100) {
                throw ValidationException::withMessages(['bulkSapInput' => 'SAP code exceeds the 100-character database limit.']);
            }
            // SAP codes are stored as text; preserve leading zeroes.
            if (isset($unique[$code])) {
                $duplicates[$code] = ($duplicates[$code] ?? 0) + 1;
            } else {
                $unique[$code] = true;
            }
        }
        $codes = array_keys($unique);
        $products = collect();
        foreach (array_chunk($codes, 400) as $chunk) {
            $products = $products->concat(Product::query()->whereIn('sap_code', $chunk)->get(['id', 'sap_code', 'product_name']));
        }
        $byCode = $products->groupBy('sap_code');
        $matched = [];
        $missing = [];
        $ambiguous = [];
        foreach ($codes as $code) {
            $rows = $byCode->get($code, collect());
            if ($rows->isEmpty()) {
                $missing[] = $code;
            } elseif ($rows->count() > 1) {
                $ambiguous[] = $code;
            } else {
                $matched[] = ['code' => $code, 'id' => $rows->first()->id, 'name' => $rows->first()->product_name];
            }
        }
        return [
            'total_entries' => count($tokens),
            'unique_codes' => count($codes),
            'matched_count' => count($matched),
            'missing_count' => count($missing),
            'ambiguous_count' => count($ambiguous),
            'duplicate_count' => array_sum($duplicates),
            'matched_preview' => array_slice($matched, 0, 20),
            'missing_preview' => array_slice($missing, 0, 100),
            'ambiguous_preview' => array_slice($ambiguous, 0, 100),
            'duplicate_preview' => array_slice(array_keys($duplicates), 0, 100),
            'has_issues' => (bool) ($missing || $ambiguous || $duplicates),
        ];
    }
}
