<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permissions = [
            'iar.create',
            'iar.view.own',
            'iar.view.assigned',
            'iar.view.all',
            'iar.export.own',
            'iar.export.all',
            'assessment.edit.assigned',
            'assessment.submit.assigned',
            'assessment.reopen',
            'master.products.manage',
            'master.assessors.manage',
            'master.users.manage',
            'master.import',
            'audit.view',
        ];
        foreach ($permissions as $permission)
            Permission::findOrCreate($permission, 'web');
        $matrix = [
            'super-admin' => $permissions,
            'global-ra' => ['iar.create', 'iar.view.own', 'iar.view.assigned', 'iar.export.own', 'assessment.edit.assigned', 'assessment.submit.assigned'],
            'local-ra' => ['iar.view.assigned', 'assessment.edit.assigned', 'assessment.submit.assigned'],
            'master-data-manager' => ['master.products.manage', 'master.assessors.manage', 'master.import'],
            'read-only-reviewer' => ['iar.view.all', 'iar.export.all'],
        ];
        foreach ($matrix as $roleName => $allowed) {
            Role::findOrCreate($roleName, 'web')->syncPermissions($allowed);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
