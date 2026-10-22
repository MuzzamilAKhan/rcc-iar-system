from pathlib import Path
import shutil
root=Path(__file__).resolve().parent
project=Path(r'C:\wamp64\www\rcc-iar-system')
assert (project/'artisan').is_file(), f'Laravel project not found: {project}'
routes=project/'routes/web.php'
src=routes.read_text(encoding='utf-8')
needle="->name('iar.readiness');"
if needle not in src:
    addition='''

// Phase 5C.1: read-only submission readiness preview.
\\Illuminate\\Support\\Facades\\Route::middleware(['auth', 'active'])
    ->get('/iars/{impactAssessment}/readiness', function (
        \\App\\Models\\ImpactAssessment $impactAssessment,
        \\App\\Services\\Iar\\IarSubmissionReadiness $readiness
    ) {
        $user = auth()->user();
        abort_unless(
            $user->can('iar.view.all') ||
            ($impactAssessment->creator_id === $user->id && $user->can('iar.view.own')),
            403
        );
        $report = $readiness->inspect($impactAssessment);
        return view('iar.readiness', compact('impactAssessment', 'report'));
    })
    ->name('iar.readiness');
'''
    routes.write_text(src.rstrip()+addition,encoding='utf-8')
    print('Route added to routes/web.php')
else:
    print('Route already exists; skipping')
for rel in ['app/Services/Iar/IarSubmissionReadiness.php','resources/views/iar/readiness.blade.php','tests/Feature/IarSubmissionReadinessTest.php']:
    target=project/rel
    target.parent.mkdir(parents=True,exist_ok=True)
    if target.exists():
        raise RuntimeError(f'Refusing overwrite existing file: {target}')
    shutil.copy2(root/rel,target)
    print('Installed',rel)
print('Open /iars/1/readiness for an accessible IAR ID. Do not run migrations or seeds.')
