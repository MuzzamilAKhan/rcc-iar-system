RCC IAR - ClientMasterDataImporter header fix

1. Back up app/Services/Import/ClientMasterDataImporter.php in your Laravel project.
2. Copy app/Services/Import/ClientMasterDataImporter.php from this ZIP into the matching path.
3. From C:\wamp64\www\rcc-iar-system run:
   php artisan optimize:clear
   php artisan db:seed --class=ClientSampleDataSeeder
   php artisan test
4. Inspect master_import_issues after the import. Real missing emails, unknown markets,
   and regulatory statuses are still recorded as issues.

The patch normalizes CSV headers (including BOM, extra quote marks and whitespace),
rejects duplicate normalized headers, and clears stale missing_bu/unmapped_country issues
when an individual row is now successfully mapped. It does not reset tables or users.

WARNING: A successful import does NOT mean that contact user accounts are approved
for login or that country/regulatory mapping exceptions are resolved.
