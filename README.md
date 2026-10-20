# RCC-IAR — Phase 3 Client Master Data Import (Laravel 12 / Livewire 4)

## IMPORTANT: Private client data

This ZIP contains the client-provided **real contact emails** and MAT export. Keep this ZIP local and PRIVATE. **Do not commit `storage/app/private/iar-import/` or the ZIP to a public GitHub repository.** The provided `.gitignore` snippet excludes this source directory. Export and application credentials must stay outside Git.

## Data assessment

| Input | Observations |
|---|---|
| Global RA CSV | 50 records; 49 unique emails; multiple BU assignments supported by `user_business_units`. |
| Local RA CSV | 149 rows, 145 country labels, 39 missing emails; 4 labels have more than one entry. |
| MAT export | 285 data rows, 48 columns, 69 distinct populated SAP identifiers; 11 market names; 14 rows without country. |

All MAT source columns are preserved in `master_import_rows.source_data`; country-registration row detail also preserved in `product_registrations.source_metadata`. Blank/ambiguous entries generate `master_import_issues` rather than being silently invented.

### Current source-specific decisions (NOT client-approved yet)

- Source `REGULATORY_CERT_STATUS` beginning `Active:` -> internal `registration_status=active`; `Inactive:` -> `inactive`; blanks -> `unknown`. Country commercial status is stored separately, **not** used as regulatory status.
- `CEE (EU)` and `EU` use creator assignment; `United Kingdom` and `USA` override their source region labels; all other regions use country assessor. Confirm before production.
- The importer supports one primary and one backup assessor; mapping order follows the local CSV. Please confirm the backup is indeed the second row, not a separate escalation contact.
- The workbook has no proven global SAP uniqueness beyond this sample; product master `firstOrCreate(sap_code)` assumes that one SAP identifies one product. Product-country registrations are distinct rows.
- Source country code lookup includes verified ISO mapping where unambiguous; unmapped names stay in import issues and source staging (never auto-mapped to the wrong country).
- All contacts are imported as **inactive users with randomized unshared passwords**. Admin provisioning and approved activation remain required. Never email those generated passwords.

## Installation (Windows CMD)

1. **Back up database first**. Check `git status` and create a local project backup.
2. Extract this package over `C:\wamp64\www\rcc-iar-system` while preserving your existing `.env`, `User.php`, Fortify provider, and already-run migrations. **Do not overwrite Phase 2 migration files.** The package includes only a NEW migration, two extended existing models, one importer, one seeder, a test, and private data.
3. Append this line to project-root `.gitignore`:

   ```gitignore
   /storage/app/private/iar-import/
   ```

4. Verify `app/Models/User.php` still has Spatie `HasRoles` trait (already done in your local project).
5. Run:

   ```cmd
   cd C:\wamp64\www\rcc-iar-system
   php artisan migrate
   php artisan db:seed --class=ClientSampleDataSeeder
   php artisan test
   ```

6. Verify via Tinker (do not print personal contact details in public logs):

   ```php
   DB::table('master_import_rows')->selectRaw('source_file, count(*) as records')->groupBy('source_file')->get();
   DB::table('master_import_issues')->selectRaw('issue_code, count(*) as total')->groupBy('issue_code')->get();
   DB::table('products')->count();
   DB::table('product_registrations')->count();
   ```

## Security: add explicit inactive-user login rejection

`users.is_active` does not automatically block Fortify authentication. In your existing `app/Providers/FortifyServiceProvider.php` `boot()` method, add:

```php
Fortify::authenticateUsing(function (\Illuminate\Http\Request $request) {
    $user = \App\Models\User::where('email', strtolower(trim((string) $request->input('email'))))->first();
    return $user && $user->is_active && \Illuminate\Support\Facades\Hash::check(
        (string) $request->input('password'), $user->password
    ) ? $user : null;
});
```

Never expose imported emails to public registration, password reset, or privileged roles without an approved onboarding process. Consider enforcing active user checks on every authenticated request as well (to revoke an active existing session after a user is deactivated).

## What is NOT implemented yet

- Human review/approval workflow to resolve `master_import_issues`, country aliases, and source-region conflicts.
- A per-product immutable registration/source snapshot, detailed registration-version handling, true multi-SAP identity policy.
- Create IAR UI, SAP matching/assignment service, completed-assessment guard, email notifications, Excel exports.
- Admin CRUD UI, access policies, permission middleware and active-user session enforcement.

No production IAR should be issued until the contact list and country mapping exceptions are reviewed and actual client approval obtained.

## Idempotency / non-destructive policy

Repeated import does not delete existing users or assessments and does not replace existing passwords, activation statuses, or roles. Updated MAT values for known products and registrations can be refreshed. For production, replace this development importer with a controlled batch version with human preview, before/after logs, explicit record keys and rollback capability.
