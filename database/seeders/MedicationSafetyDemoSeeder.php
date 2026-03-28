<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\DrugInteractionRule;
use App\Models\Medicine;
use App\Models\MedicineReorderPolicy;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MedicationSafetyDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedMedicineUnitsAndPolicies();
        $this->seedDrugInteractionRules();
    }

    private function seedMedicineUnitsAndPolicies(): void
    {
        $branches = Branch::query()->whereIn('code', ['MAIN', 'BDG', 'MDN'])->get()->keyBy('code');
        $suppliers = Supplier::query()->where('is_active', true)->orderBy('id')->take(3)->get();

        if ($branches->isEmpty() || $suppliers->isEmpty()) {
            return;
        }

        $medicineDefinitions = [
            [
                'code' => 'PCM500',
                'units' => [
                    ['label' => 'STRIP', 'conversion_factor' => 10, 'allow_purchase' => true, 'allow_dispense' => true, 'sort_order' => 20],
                    ['label' => 'BOX', 'conversion_factor' => 100, 'allow_purchase' => true, 'allow_dispense' => false, 'sort_order' => 30],
                ],
                'policies' => [
                    [
                        'branch' => 'MAIN',
                        'preferred_purchase_label' => 'STRIP',
                        'minimum_stock' => 120,
                        'safety_stock' => 40,
                        'reorder_point' => 320,
                        'reorder_quantity' => 200,
                        'lead_time_days' => 5,
                        'notes' => 'Paracetamol fast moving item untuk counter utama.',
                        'suppliers' => [
                            ['index' => 0, 'priority' => 10, 'is_primary' => true],
                            ['index' => 1, 'priority' => 20, 'is_primary' => false],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'AMX500',
                'units' => [
                    ['label' => 'STRIP', 'conversion_factor' => 10, 'allow_purchase' => true, 'allow_dispense' => true, 'sort_order' => 20],
                    ['label' => 'BOX', 'conversion_factor' => 100, 'allow_purchase' => true, 'allow_dispense' => false, 'sort_order' => 30],
                ],
                'policies' => [
                    [
                        'branch' => 'BDG',
                        'preferred_purchase_label' => 'BOX',
                        'minimum_stock' => 80,
                        'safety_stock' => 30,
                        'reorder_point' => 140,
                        'reorder_quantity' => 100,
                        'lead_time_days' => 7,
                        'notes' => 'Antibiotik fast moving untuk branch Bandung.',
                        'suppliers' => [
                            ['index' => 0, 'priority' => 10, 'is_primary' => true],
                            ['index' => 2, 'priority' => 30, 'is_primary' => false],
                        ],
                    ],
                ],
            ],
            [
                'code' => 'ALB60',
                'units' => [
                    ['label' => 'PACK', 'conversion_factor' => 12, 'allow_purchase' => true, 'allow_dispense' => false, 'sort_order' => 20],
                ],
                'policies' => [
                    [
                        'branch' => 'MDN',
                        'preferred_purchase_label' => 'PACK',
                        'minimum_stock' => 24,
                        'safety_stock' => 12,
                        'reorder_point' => 36,
                        'reorder_quantity' => 24,
                        'lead_time_days' => 10,
                        'notes' => 'Buffer bronchodilator syrup untuk cabang Medan.',
                        'suppliers' => [
                            ['index' => 1, 'priority' => 10, 'is_primary' => true],
                            ['index' => 2, 'priority' => 20, 'is_primary' => false],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($medicineDefinitions as $definition) {
            $medicine = Medicine::query()->where('code', $definition['code'])->first();

            if (! $medicine) {
                continue;
            }

            foreach ($definition['units'] as $unitDefinition) {
                $medicine->units()->updateOrCreate(
                    ['label' => $unitDefinition['label']],
                    [
                        'conversion_factor' => $unitDefinition['conversion_factor'],
                        'is_base' => false,
                        'allow_purchase' => $unitDefinition['allow_purchase'],
                        'allow_dispense' => $unitDefinition['allow_dispense'],
                        'sort_order' => $unitDefinition['sort_order'],
                        'is_active' => true,
                    ],
                );
            }

            $medicine->load('units');

            foreach ($definition['policies'] as $policyDefinition) {
                $branch = $branches->get($policyDefinition['branch']);

                if (! $branch) {
                    continue;
                }

                $preferredUnit = $medicine->units->firstWhere('label', $policyDefinition['preferred_purchase_label']);

                $policy = MedicineReorderPolicy::query()->updateOrCreate(
                    [
                        'medicine_id' => $medicine->id,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'preferred_purchase_unit_id' => $preferredUnit?->id,
                        'minimum_stock' => $policyDefinition['minimum_stock'],
                        'safety_stock' => $policyDefinition['safety_stock'],
                        'reorder_point' => $policyDefinition['reorder_point'],
                        'reorder_quantity' => $policyDefinition['reorder_quantity'],
                        'lead_time_days' => $policyDefinition['lead_time_days'],
                        'notes' => $policyDefinition['notes'],
                        'is_active' => true,
                    ],
                );

                foreach ($policyDefinition['suppliers'] as $supplierDefinition) {
                    $supplier = $suppliers->get($supplierDefinition['index']);

                    if (! $supplier) {
                        continue;
                    }

                    $policy->supplierPreferences()->updateOrCreate(
                        ['supplier_id' => $supplier->id],
                        [
                            'priority' => $supplierDefinition['priority'],
                            'is_primary' => $supplierDefinition['is_primary'],
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    private function seedDrugInteractionRules(): void
    {
        $definitions = [
            [
                'code' => 'DIR-001',
                'left_operand_type' => 'ingredient',
                'left_operand_value' => 'omeprazole',
                'right_operand_type' => 'ingredient',
                'right_operand_value' => 'clopidogrel',
                'severity' => 'major',
                'title' => 'Omeprazole may reduce clopidogrel activation',
                'clinical_effect' => 'Omeprazole dapat menurunkan pembentukan metabolit aktif clopidogrel sehingga efek antiplatelet berkurang.',
                'management_advice' => 'Pertimbangkan PPI alternatif atau dokumentasikan alasan override bila kombinasi tetap dipakai.',
            ],
            [
                'code' => 'DIR-002',
                'left_operand_type' => 'class',
                'left_operand_value' => 'antihistamine',
                'right_operand_type' => 'class',
                'right_operand_value' => 'antihistamine',
                'severity' => 'major',
                'title' => 'Duplicate antihistamine therapy',
                'clinical_effect' => 'Kombinasi dua antihistamine dapat meningkatkan sedasi dan efek antikolinergik.',
                'management_advice' => 'Tinjau apakah memang perlu dua antihistamine. Override hanya bila ada alasan klinis yang kuat.',
            ],
            [
                'code' => 'DIR-003',
                'left_operand_type' => 'ingredient',
                'left_operand_value' => 'warfarin',
                'right_operand_type' => 'ingredient',
                'right_operand_value' => 'amoxicillin',
                'severity' => 'moderate',
                'title' => 'Warfarin with amoxicillin may increase bleeding risk',
                'clinical_effect' => 'Penggunaan bersamaan dapat meningkatkan INR atau risiko perdarahan pada pasien sensitif.',
                'management_advice' => 'Pantau tanda perdarahan dan pertimbangkan monitoring INR bila kombinasi dipakai.',
            ],
            [
                'code' => 'DIR-004',
                'left_operand_type' => 'ingredient',
                'left_operand_value' => 'sildenafil',
                'right_operand_type' => 'ingredient',
                'right_operand_value' => 'nitrate',
                'severity' => 'contraindicated',
                'title' => 'Sildenafil with nitrate is contraindicated',
                'clinical_effect' => 'Kombinasi dapat menyebabkan hipotensi berat yang membahayakan pasien.',
                'management_advice' => 'Jangan gunakan bersamaan. Pilih alternatif terapi yang lebih aman.',
            ],
        ];

        foreach ($definitions as $definition) {
            DrugInteractionRule::query()->updateOrCreate(
                ['code' => $definition['code']],
                $definition + ['is_active' => true],
            );
        }
    }
}
