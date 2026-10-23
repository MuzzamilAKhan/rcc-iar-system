<?php

namespace App\Services\Iar;

use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

final class ReadIarSapSpreadsheet
{
    private const MAX_CODES = 10000;
    private const HEADERS = ['sapcode', 'sapnumber', 'sap', 'materialnumber', 'materialcode', 'material'];

    /** Extract SAP codes as strings. Uploaded documents never update products or send emails. */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);
        if (!in_array($extension, ['xlsx', 'csv'], true)) {
            throw ValidationException::withMessages(['sapExcelFile' => 'Upload an .xlsx or .csv file.']);
        }

        try {
            $reader = $extension === 'xlsx' ? new Xlsx() : new Csv();
            if ($extension === 'csv') {
                $first = (string) file_get_contents($path, false, null, 0, 4096);
                $firstLine = strtok($first, "\r\n") ?: '';
                $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
                arsort($counts);
                $reader->setDelimiter((string) array_key_first($counts));
                $reader->setInputEncoding('UTF-8');
            }
            // Retain display formatting, including number formats representing leading zeroes.
            // Never evaluate spreadsheet formulas.
            $reader->setReadDataOnly(false);
            $book = $reader->load($path);
            try {
                $sheet = $book->getSheet(0);
                $lastRow = $sheet->getHighestDataRow();
                $lastColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
                if ($lastRow > self::MAX_CODES + 1) {
                    throw ValidationException::withMessages(['sapExcelFile' => 'Excel file has more than 10,000 rows. Split it into smaller files.']);
                }
                if ($lastColumn > 50) {
                    throw ValidationException::withMessages(['sapExcelFile' => 'Use a SAP code column within the first 50 columns.']);
                }
                $columns = min($lastColumn, 50);
                $sapColumn = null;
                for ($c = 1; $c <= $columns; $c++) {
                    $value = $this->cell($sheet, 1, $c);
                    $normalized = preg_replace('/[^a-z0-9]/', '', strtolower($value));
                    if (in_array($normalized, self::HEADERS, true)) {
                        $sapColumn = $c;
                        break;
                    }
                }
                if ($sapColumn === null && $lastColumn > 1) {
                    throw ValidationException::withMessages(['sapExcelFile' => 'Multiple columns found, but no SAP Code header. Name the SAP column "SAP Code".']);
                }
                $sapColumn ??= 1;
                $startRow = $this->isHeader($this->cell($sheet, 1, $sapColumn)) ? 2 : 1;
                $codes = [];
                for ($r = $startRow; $r <= $lastRow; $r++) {
                    $value = $this->cell($sheet, $r, $sapColumn);
                    if ($value === '') continue;
                    if (mb_strlen($value) > 100) {
                        throw ValidationException::withMessages(['sapExcelFile' => "SAP code at row {$r} exceeds 100 characters."]);
                    }
                    $codes[] = $value;
                    if (count($codes) > self::MAX_CODES) {
                        throw ValidationException::withMessages(['sapExcelFile' => 'Maximum 10,000 SAP codes per file.']);
                    }
                }
                if ($codes === []) {
                    throw ValidationException::withMessages(['sapExcelFile' => 'No SAP codes found in the first worksheet.']);
                }
                return $codes;
            } finally {
                $book->disconnectWorksheets();
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['sapExcelFile' => 'Unable to read Excel file. Check that it is a valid, unencrypted .xlsx or .csv.']);
        }
    }

    private function isHeader(string $value): bool
    {
        return in_array(preg_replace('/[^a-z0-9]/', '', strtolower($value)), self::HEADERS, true);
    }

    private function cell($sheet, int $row, int $col): string
    {
        $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($col) . $row);
        if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
            throw ValidationException::withMessages(['sapExcelFile' => "Formula at row {$row} is not allowed in SAP import."]);
        }
        return trim((string) $cell->getFormattedValue());
    }
}
