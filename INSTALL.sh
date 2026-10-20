#!/usr/bin/env bash
set -euo pipefail
cat <<'MESSAGE'
INSTALL MANUALLY: This archive is a SOURCE OVERLAY, not a full Laravel project.
1. laravel new regulatory-iar  # select Laravel 12 + Livewire + MySQL + PHPUnit
2. cd regulatory-iar
3. composer require spatie/laravel-permission:^7.0
4. php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
5. Configure .env database, then copy OVERLAY CONTENTS into project root.
6. php artisan migrate --seed && npm install && npm run build && php artisan test
For additional cautions, see README.md.
MESSAGE
