<?php

namespace Database\Seeders;

use App\Modules\Access\Services\AccessService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(AccessService::class)->syncConfiguredAccess();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
