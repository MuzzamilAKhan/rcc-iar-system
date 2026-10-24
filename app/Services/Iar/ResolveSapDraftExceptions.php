<?php

namespace App\Services\Iar;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Re-resolve user input at save time. Never rely on stale preview or hidden form IDs. */
final class ResolveSapDraftExceptions
{
    public function resolve(string $input, bool $override, ?string $reason): array
    {
        $tokens = preg_split('/[\r\n\t,;]+/u', $input) ?: [];
        $tokens = array_values(array_filter(array_map('trim', $tokens), static fn ($v) => $v !== ''));
        if (count($tokens) < 1 || count($tokens) > 10000) {
            throw ValidationException::withMessages(['bulkSapInput' => 'Enter between 1 and 10,000 SAP codes.']);
        }
        foreach ($tokens as $code) {
            if (mb_strlen($code) > 100) {
                throw ValidationException::withMessages(['bulkSapInput' => 'A SAP code exceeds 100 characters.']);
            }
        }
        $codes = array_values(array_unique($tokens));
        $found = collect();
        foreach (array_chunk($codes, 400) as $part) {
            $found = $found->concat(Product::query()->whereIn('sap_code', $part)->get(['id', 'sap_code']));
        }
        $groups = $found->groupBy('sap_code');
        $missing = [];
        $ambiguous = [];
        $rows = [];
        foreach ($codes as $code) {
            $matches = $groups->get($code, collect());
            if ($matches->count() > 1) {
                $ambiguous[] = $code;
            } elseif ($matches->isEmpty()) {
                $missing[] = $code;
                $rows[] = ['product_id' => null, 'submitted_sap_code' => $code];
            } else {
                $rows[] = ['product_id' => $matches->first()->id, 'submitted_sap_code' => $code];
            }
        }
        if ($ambiguous) {
            throw ValidationException::withMessages(['bulkSapInput' => count($ambiguous) . ' ambiguous SAP code(s) match multiple products; resolve duplicate registry entries before saving (e.g. ' . implode(', ', array_slice($ambiguous, 0, 5)) . ').']);
        }
        $reason = trim((string) $reason);
        if ($missing && !$override) {
            throw ValidationException::withMessages(['overrideWarnings' => count($missing) . ' SAP code(s) are missing from Product Registry. Tick override and provide a justification to retain these unresolved items in the draft.']);
        }
        if ($missing && mb_strlen($reason) < 15) {
            throw ValidationException::withMessages(['overrideReason' => 'Please enter a meaningful override reason (at least 15 characters).']);
        }
        if (mb_strlen($reason) > 2000) {
            throw ValidationException::withMessages(['overrideReason' => 'Override reason must not exceed 2,000 characters.']);
        }
        return ['rows' => $rows, 'unresolved' => $missing, 'reason' => $missing ? $reason : null];
    }
}
