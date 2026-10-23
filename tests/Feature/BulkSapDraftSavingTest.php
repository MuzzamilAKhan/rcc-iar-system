<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Iar\ResolveBulkIarProducts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BulkSapDraftSavingTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_45_synthetic_codes_and_deduplicates_excel_paste(): void
    {
        foreach (range(1, 45) as $n) {
            Product::create(['sap_code' => sprintf('MOCK-BULK-%05d', $n), 'product_name' => 'Synthetic product ' . $n]);
        }
        $text = implode("\r\n", array_map(fn ($n) => sprintf('MOCK-BULK-%05d', $n), range(1, 45)));
        $products = app(ResolveBulkIarProducts::class)->resolve($text . "\r\nMOCK-BULK-00001");
        $this->assertCount(45, $products);
        $this->assertEquals(45, $products->pluck('sap_code')->unique()->count());
    }

    public function test_unknown_code_blocks_save_resolution(): void
    {
        Product::create(['sap_code' => 'MOCK-KNOWN', 'product_name' => 'Synthetic known product']);
        $this->expectException(ValidationException::class);
        app(ResolveBulkIarProducts::class)->resolve("MOCK-KNOWN\nMOCK-MISSING");
    }

    public function test_ambiguous_sap_code_blocks_save_resolution(): void
    {
        Product::create(['sap_code' => 'MOCK-DUP', 'product_name' => 'Synthetic one']);
        Product::create(['sap_code' => 'MOCK-DUP', 'product_name' => 'Synthetic two']);
        $this->expectException(ValidationException::class);
        app(ResolveBulkIarProducts::class)->resolve('MOCK-DUP');
    }
}
