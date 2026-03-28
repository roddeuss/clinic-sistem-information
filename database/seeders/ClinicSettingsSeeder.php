<?php

namespace Database\Seeders;

use App\Models\Clinic;
use Illuminate\Database\Seeder;

class ClinicSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::query()->updateOrCreate(
            ['code' => 'CSI'],
            [
                'name' => 'CSI Clinic',
                'logo_path' => null,
                'phone' => '021-555-0199',
                'email' => 'hello@csiclinic.local',
                'address' => 'Jl. Operasional Klinik No. 1, Jakarta',
                'invoice_header' => "CSI Clinic\nSistem Informasi Klinik Terintegrasi",
            ],
        );

        $clinic->branches()->updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Cabang Utama',
                'phone' => '021-555-0101',
                'address' => 'Jl. Operasional Klinik No. 1, Jakarta',
                'opening_time' => '08:00',
                'closing_time' => '20:00',
                'queue_prefix' => 'A',
                'queue_number_padding' => 3,
                'is_active' => true,
            ],
        );
    }
}
