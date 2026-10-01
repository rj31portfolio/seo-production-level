<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('agencyos.roles') as $name => $permissions) {
            if ($name === 'agency_owner') {
                $permissions = array_merge($permissions, config('agencyos.billing_permissions'));
            }
            $permissions = array_merge($permissions, config('seo_tools.role_permissions.'.$name, []));
            $role = Role::firstOrCreate(['name' => $name]);
            $role->permissions()->sync(array_map(fn ($name) => Permission::firstOrCreate(['name' => $name])->id, $permissions));
        }
    }
}
