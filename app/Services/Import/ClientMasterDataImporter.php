<?php

namespace App\Services\Import;

use App\Models\{BusinessUnit, Country, CountryAssessor, Product, ProductRegistration, Region, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/** Imports data supplied by the client. Never creates usable passwords for imported contacts. */
final class ClientMasterDataImporter
{
    private const ROOT = 'app/private/iar-import';

    public function run(): array
    {
        $root = storage_path(self::ROOT);
        foreach (['local_ra.csv', 'global_ra.csv', 'mat_rows.csv', 'country_codes.json'] as $file) {
            if (!is_file("{$root}/{$file}")) {
                throw new RuntimeException("Missing local import file: {$root}/{$file}");
            }
        }
        $countryCodes = json_decode(file_get_contents("{$root}/country_codes.json"), true, flags: JSON_THROW_ON_ERROR);
        $stats = [];
        $stats['global'] = $this->importGlobal("{$root}/global_ra.csv");
        $stats['local'] = $this->importLocal("{$root}/local_ra.csv", $countryCodes);
        $stats['mat'] = $this->importMat("{$root}/mat_rows.csv", $countryCodes);
        $stats['issues'] = DB::table('master_import_issues')->count();
        return $stats;
    }

    private function readCsv(string $path): \Generator
    {
        $stream = fopen($path, 'rb');
        if ($stream === false)
            throw new RuntimeException("Cannot read {$path}");
        try {
            $headers = fgetcsv($stream);
            if ($headers === false)
                throw new RuntimeException("Empty CSV: {$path}");
            $headers = array_map(fn($s) => trim((string) $s, "\xEF\xBB\xBF \t\r\n"), $headers);
            $rowNumber = 1;
            while (($values = fgetcsv($stream)) !== false) {
                $rowNumber++;
                if (count($values) === 1 && trim($values[0]) === '')
                    continue;
                $values = array_pad($values, count($headers), '');
                $row = array_combine($headers, array_slice($values, 0, count($headers)));
                yield [$rowNumber, array_map(fn($s) => trim((string) $s), $row)];
            }
        } finally {
            fclose($stream);
        }
    }

    private function issue(string $source, int $row, string $code, string $message, array $data): void
    {
        DB::table('master_import_issues')->updateOrInsert(
            ['source_file' => $source, 'source_row' => $row, 'issue_code' => $code],
            [
                'message' => $message,
                'source_row_data' => json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    private function stage(string $source, int $number, string $kind, array $row): void
    {
        DB::table('master_import_rows')->updateOrInsert(
            ['source_file' => $source, 'source_row' => $number],
            [
                'record_kind' => $kind,
                'source_fingerprint' => hash('sha256', json_encode($row)),
                'source_data' => json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE),
                'created_at' => now(),
                'updated_at' => now()
            ]
        );
    }

    private function account(string $name, string $email, ?string $position = null): User
    {
        // Never enable external contacts or grant them a known/static password.
        $user = User::firstOrCreate(['email' => strtolower(trim($email))], [
            'name' => $name,
            'position' => $position,
            'password' => Hash::make(Str::random(72)),
            'is_active' => false,
        ]);
        // Existing user state/password/roles are not overwritten by import.
        return $user;
    }

    private function importGlobal(string $file): int
    {
        $count = 0;
        foreach ($this->readCsv($file) as [$num, $row]) {
            DB::transaction(function () use ($num, $row, &$count) {
                $this->stage('global_ra.csv', $num, 'global_ra', $row);
                $email = strtolower($row['Email'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->issue('global_ra.csv', $num, 'invalid_email', 'Contact lacks a valid email.', $row);
                    return;
                }
                $user = $this->account($row['Full Name'] ?: $email, $email, $row['Position'] ?: null);
                $user->assignRole('global-ra');
                $code = Str::upper(trim($row['BU'] ?? ''));
                if ($code === '') {
                    $this->issue('global_ra.csv', $num, 'missing_bu', 'Business unit is missing.', $row);
                    return;
                }
                $bu = BusinessUnit::firstOrCreate(['code' => $code], ['name' => $code]);
                if ($user->business_unit_id === null)
                    $user->update(['business_unit_id' => $bu->id]);
                DB::table('user_business_units')->updateOrInsert(
                    ['user_id' => $user->id, 'business_unit_id' => $bu->id],
                    [
                        'product_process_expertise' => $row['Product/Process Expertise'] ?: null,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]
                );
                $count++;
            });
        }
        return $count;
    }

    private function canonicalRegion(string $country, string $rawRegion): string
    {
        if (strcasecmp($country, 'United Kingdom') === 0)
            return 'UK';
        if (strcasecmp($country, 'USA') === 0 || strcasecmp($rawRegion, 'US') === 0)
            return 'USA';
        if (in_array($rawRegion, ['EU', 'CEE (EU)'], true))
            return 'EU';
        return 'OTHER';
    }

    private function resolveCountry(string $name, array $codes, string $source, int $num, array $row, string $rawRegion = 'UNKNOWN'): ?Country
    {
        $value = $codes[$name] ?? null;
        if (!is_array($value) || empty($value['iso2']) || empty($value['iso3'])) {
            $this->issue($source, $num, 'unmapped_country', "No approved ISO mapping for: {$name}", $row);
            return null;
        }
        $canonical = $this->canonicalRegion($name, $rawRegion);
        $region = Region::firstOrCreate(['code' => $canonical], [
            'name' => $canonical,
            'assignment_strategy' => $canonical === 'OTHER' ? 'country_assessor' : 'creator',
        ]);
        $country = Country::firstOrCreate(['iso2' => $value['iso2']], [
            'iso3' => $value['iso3'],
            'name' => $name,
            'region_id' => $region->id,
        ]);
        // A local contact mapping is authoritative for region; MAT alone must not override it.
        if ($source === 'local_ra.csv' && $country->region_id !== $region->id) {
            $this->issue($source, $num, 'region_mismatch', "Existing country region differs from incoming region {$rawRegion}", $row);
        }
        return $country;
    }

    private function importLocal(string $file, array $codes): int
    {
        $count = 0;
        foreach ($this->readCsv($file) as [$num, $row]) {
            DB::transaction(function () use ($num, $row, $codes, &$count) {
                $this->stage('local_ra.csv', $num, 'local_ra', $row);
                $name = $row['Title'] ?? '';
                $rawRegion = $row['CvT Region'] ?? '';
                $country = $this->resolveCountry($name, $codes, 'local_ra.csv', $num, $row, $rawRegion);
                if (!$country)
                    return;
                $email = strtolower($row['Email'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->issue('local_ra.csv', $num, 'unassigned_contact', 'No assignable email. Contact label preserved in staging.', $row);
                    return;
                }
                $user = $this->account($row['CvT RA Contact'] ?: $email, $email);
                $user->assignRole('local-ra');
                $existing = CountryAssessor::where('country_id', $country->id)->orderBy('assignment_priority')->get();
                if ($existing->contains('user_id', $user->id)) {
                    $count++;
                    return;
                }
                if ($existing->count() >= 2) {
                    $this->issue('local_ra.csv', $num, 'too_many_assessors', 'More than two unique country assessors.', $row);
                    return;
                }
                CountryAssessor::create([
                    'country_id' => $country->id,
                    'user_id' => $user->id,
                    'assignment_priority' => $existing->count() + 1,
                    'is_active' => true,
                ]);
                $count++;
            });
        }
        return $count;
    }

    private function importMat(string $file, array $codes): int
    {
        $count = 0;
        foreach ($this->readCsv($file) as [$num, $row]) {
            DB::transaction(function () use ($num, $row, $codes, &$count) {
                $this->stage('mat_rows.csv', $num, 'mat', $row);
                $sap = trim($row['SAP_MATERIAL_ID'] ?? '');
                if ($sap === '') {
                    $this->issue('mat_rows.csv', $num, 'missing_sap', 'Row does not identify an SAP material.', $row);
                    return;
                }
                $buCode = Str::upper(trim($row['BUSINESS_UNIT_DESC'] ?? ''));
                $bu = $buCode !== '' ? BusinessUnit::firstOrCreate(['code' => $buCode], ['name' => $buCode]) : null;
                // SAP can repeat by country; source workbook uses one distinct material identity per SAP.
                $product = Product::firstOrCreate(['sap_code' => $sap], [
                    'product_name' => $row['SAP_MATERIAL_DESC'] ?: $sap,
                    'brand' => $row['BRAND_DESC'] ?: null,
                    'icc_code' => $row['ICC_CODE'] ?: null,
                    'business_unit_id' => $bu?->id,
                ]);
                $product->update([
                    'uno_medical_code' => $row['UNO_MEDICAL_CODE'] ?: null,
                    'other_erp_code' => $row['OTHER_ERP_CODE'] ?: null,
                    'product_code_on_label' => $row['PRODUCT_CODE_ON_LABEL'] ?: null,
                    'global_commercial_status' => $row['GLOBAL_COMMERCIAL_STATUS'] ?: null,
                ]);
                $name = $row['COUNTRY_NAME'] ?? '';
                if ($name === '') {
                    $this->issue('mat_rows.csv', $num, 'no_market', 'Product has no country registration; product imported only.', $row);
                    return;
                }
                $country = $this->resolveCountry($name, $codes, 'mat_rows.csv', $num, $row);
                if (!$country)
                    return;
                $status = $row['REGULATORY_CERT_STATUS'] ?? '';
                // Regulatory status is NOT inferred from global commercial status.
                $normalized = str_starts_with(strtolower($status), 'active:') ? 'active'
                    : (str_starts_with(strtolower($status), 'inactive:') ? 'inactive' : 'unknown');
                if ($normalized === 'unknown')
                    $this->issue('mat_rows.csv', $num, 'unknown_registration_status', 'No confirmed regulatory registration status.', $row);
                $record = ProductRegistration::firstOrNew([
                    'product_id' => $product->id,
                    'country_id' => $country->id,
                    'registration_number' => $row['PRIMARY_CERTIFICATE_NBR'] ?: null,
                ]);
                $record->fill([
                    'registration_status' => $normalized,
                    'commercial_status' => $row['COUNTRY_COMMERCIAL_STATUS'] ?: null,
                    'source_registration_status' => $status ?: null,
                    'source_commercial_status' => $row['COUNTRY_COMMERCIAL_STATUS'] ?: null,
                    'product_registration_type' => $row['PRODUCT_REGISTRATION_TYPE'] ?: null,
                    'product_description_in_market' => $row['PRODUCT_DESC_IN_MARKET'] ?: null,
                    'device_classification' => $row['CLASSIFICATION'] ?: null,
                    'initial_registration_date' => $this->excelDate($row['DATE_OF_INITIAL_LICENSE_APPROVAL'] ?? ''),
                    'certificate_issue_date' => $this->excelDate($row['PRIMARY_CERTIFICATE_ISSUE_DATE'] ?? ''),
                    'certificate_expiry_date' => $this->excelDate($row['PRIMARY_CERTIFICATE_EXPIRATION_DATE'] ?? ''),
                    'doc_number' => $row['DECLARATION_OF_CONFORMITY'] ?: null,
                    'doc_approval_date' => $this->excelDate($row['DOC_APPROVAL_DATE'] ?? ''),
                    'fda_510k_number_or_exempt' => $row['510K_EXEMPT'] ?: null,
                    'market_authorization_holder' => $row['MARKET_AUTHORIZATION_HOLDER'] ?: null,
                    'legal_manufacturer' => $row['CTRY_LEGAL_MANUFACTURER'] ?: null,
                    'physical_manufacturer' => $row['CTRY_SITE_OF_MANUFACTURE'] ?: null,
                    'source_metadata' => $row,
                ]);
                $record->save();
                $count++;
            });
        }
        return $count;
    }

    private function excelDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '')
            return null;
        // Excel 1900 serial dates: 25569 is Unix epoch (1970-01-01).
        if (is_numeric($value))
            return gmdate('Y-m-d', (int) round(((float) $value - 25569) * 86400));
        try {
            return \Carbon\CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
