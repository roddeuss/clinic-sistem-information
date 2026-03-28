<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AccessControlSeeder::class,
            ClinicSettingsSeeder::class,
            NavigationSeeder::class,
            CsiDemoSeeder::class,
            FinanceDemoSeeder::class,
            ProcurementDemoSeeder::class,
            InventoryControlDemoSeeder::class,
            MedicationSafetyDemoSeeder::class,
        ]);
    }
}
