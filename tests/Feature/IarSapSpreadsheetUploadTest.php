<?php

namespace Tests\Feature;

use App\Services\Iar\ReadIarSapSpreadsheet;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class IarSapSpreadsheetUploadTest extends TestCase
{
    public function test_imports_excel_sap_column_and_preserves_text_leading_zeroes(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mock_sap_') . '.xlsx';
        $sheet = new Spreadsheet();
        $tab = $sheet->getActiveSheet();
        $tab->setCellValue('A1', 'SAP Code');
        $tab->setCellValue('B1', 'Description');
        $tab->setCellValueExplicit('A2', '00012345', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $tab->setCellValue('B2', 'Synthetic mock product');
        $tab->setCellValue('A3', 'MOCK-00002');
        (new Xlsx($sheet))->save($file);
        $sheet->disconnectWorksheets();
        try {
            $this->assertSame(['00012345', 'MOCK-00002'], app(ReadIarSapSpreadsheet::class)->read($file, 'xlsx'));
        } finally {
            @unlink($file);
        }
    }

    public function test_reads_simple_csv_column(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mock_sap_');
        file_put_contents($file, "SAP Code\nMOCK-00001\nMOCK-00002\n");
        try {
            $this->assertSame(['MOCK-00001', 'MOCK-00002'], app(ReadIarSapSpreadsheet::class)->read($file, 'csv'));
        } finally {
            @unlink($file);
        }
    }

    public function test_rejects_multiple_columns_without_sap_header(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mock_sap_');
        file_put_contents($file, "Part,Description\nMOCK-00001,Mock product\n");
        try {
            $this->expectException(ValidationException::class);
            app(ReadIarSapSpreadsheet::class)->read($file, 'csv');
        } finally {
            @unlink($file);
        }
    }

    public function test_rejects_excel_formulas(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'mock_sap_') . '.xlsx';
        $book = new Spreadsheet();
        $book->getActiveSheet()->setCellValue('A1', 'SAP Code');
        $book->getActiveSheet()->setCellValue('A2', '=1+2');
        (new Xlsx($book))->save($file);
        $book->disconnectWorksheets();
        try {
            $this->expectException(ValidationException::class);
            app(ReadIarSapSpreadsheet::class)->read($file, 'xlsx');
        } finally {
            @unlink($file);
        }
    }
}
