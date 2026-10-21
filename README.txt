RCC-IAR Phase 5A — Local WAMP only

IMPORTANT: This is a new feature package, not a production deployment.
Unzip into C:\wamp64\www\rcc-iar-system, preserving directories.
Before unzipping, make sure Git working tree is clean or commit your own work.

Includes:
- app/Services/Iar/CreateIarDraft.php
- app/Livewire/Iar/CreateDraft.php
- resources/views/livewire/iar/create-draft.blade.php
- resources/views/iar/create.blade.php
- resources/views/iar/show.blade.php
- resources/views/dashboard.blade.php (replaces dashboard for Create IAR link)
- routes/web.php (replaces current routes preserving existing current entries)
- tests/Feature/IarDraftCreationTest.php

No migrations, existing database tables reused.

Run:
  php -l app/Services/Iar/CreateIarDraft.php
  php -l app/Livewire/Iar/CreateDraft.php
  php artisan route:list --path=iars
  php artisan test --filter=IarDraftCreationTest
  php artisan test
  npm run build

Do NOT run migrate:fresh or publish to VPS yet.

Notes:
- A draft is not submitted, assigned, mailed or activated.
- Maximum 30 selected products, one SAP code per IAR.
- Dates interpreted in configured Laravel application timezone; confirm intended timezone.
- IAR reference is IAR-YEAR-ID padded to 6 digits; IDs greater than 999999 will not match this exact test pattern.
- Product search displays first 50 matches; selected items stay selected across searches.
- Audit stores draft metadata, not the full version texts.
