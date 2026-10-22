PHASE 5C.1 - READ-ONLY IAR SUBMISSION READINESS

1. Extract ZIP anywhere (e.g. Downloads) -- NOT directly into the project.
2. Open Windows CMD:
   cd %USERPROFILE%\Downloads\phase5c1
   py install_phase5c1.py
   (Use python instead of py if necessary.)
3. In Laravel project:
   cd C:\wamp64\www\rcc-iar-system
   php artisan route:list --path=iars
   php artisan view:clear
   php artisan test
   npm run build
4. In browser use /iars/<your-existing-ID>/readiness.

The existing AssignmentReadinessService::summary() is a global report; it was not modified.
This preview adds no migrations, writes no IAR data and sends no messages.
Approval and account activation are independent; inactive contacts are BLOCKED.
No submission action is exposed. 'Ready' means preview assessment only.

LIMITATION: Registration status 'unknown' is blocked, but other source registration statuses
are not automatically interpreted as regulatory authorization or non-authorization.
Confirm acceptable registration status business rules before enabling Phase 5C.2.
