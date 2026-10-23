<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Iar\PreviewBulkSapCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkSapPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_column_paste_reports_valid_missing_and_duplicates_without_writes(): void
    {
        Product::create(['sap_code' => '00012345', 'product_name' => 'Synthetic Device']);
        $before = Product::count();
        $report = app(PreviewBulkSapCodes::class)->inspect("00012345\nNOT-FOUND\n00012345\n");
        $this->assertSame(3, $report['total_entries']);
        $this->assertSame(1, $report['matched_count']);
        $this->assertSame(1, $report['missing_count']);
        $this->assertSame(1, $report['duplicate_count']);
        $this->assertSame('00012345', $report['matched_preview'][0]['code']);
        $this->assertSame($before, Product::count());
    }

    public function test_many_synthetic_codes_are_supported_without_email_or_notifications(): void
    {
        $input = implode("\n", array_map(static fn ($i) => 'MOCK-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT), range(1, 1200)));
        $report = app(PreviewBulkSapCodes::class)->inspect($input);
        $this->assertSame(1200, $report['unique_codes']);
        $this->assertSame(1200, $report['missing_count']);
    }
}
