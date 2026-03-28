<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Counter;
use App\Models\Doctor;
use App\Models\DoctorLeave;
use App\Models\DoctorSchedule;
use App\Models\Icd10Code;
use App\Models\Invoice;
use App\Models\LaboratoryOrder;
use App\Models\LaboratoryResultEntry;
use App\Models\LaboratoryTest;
use App\Models\MedicalService;
use App\Models\Medicine;
use App\Models\MedicineBatch;
use App\Models\Prescription;
use App\Models\PrescriptionDispense;
use App\Models\ProcedureMaster;
use App\Models\ReferralDestination;
use App\Models\PatientReferral;
use App\Models\DoctorLetter;
use App\Models\VisitProcedure;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\Section;
use App\Models\User;
use App\Models\VitalSignRecord;
use App\Models\VisitMedicalService;
use App\Models\VisitRegistration;
use App\Services\ClinicalBillingService;
use App\Services\ClinicalWorkflowService;
use App\Services\PatientRecordService;
use App\Services\QueueService;
use App\Services\VisitDoctorAssignmentService;
use App\Services\BranchDocumentNumberService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class CsiDemoSeeder extends Seeder
{
    public function run(): void
    {
        $broadcastDriver = config('broadcasting.default');
        config(['broadcasting.default' => 'null']);

        try {
            $this->seedRolesAndUsers();
            $clinic = $this->seedClinicAndBranches();
            $sections = $this->seedSections($clinic);
            $this->seedCounters($clinic);
            $this->seedDoctorsAndSchedules($clinic, $sections);
            $icd10Codes = $this->seedIcd10Codes();
            $pharmacy = $this->seedPharmacyFoundation($clinic);
            $procedureMasters = $this->seedProcedureMasters($clinic);
            $medicalServices = $this->seedMedicalServices($clinic);
            $laboratoryTests = $this->seedLaboratoryTests($clinic);
            $patients = $this->seedPatients($clinic);
            $registrations = $this->seedVisitRegistrationsAndQueues($clinic, $sections, $patients);
            $this->seedClinicalDocumentation($registrations, $icd10Codes);
            $this->seedReferralAndDoctorLettersDemo($registrations);
            $this->seedFulfillmentAndBillingDemo($registrations, $pharmacy, $procedureMasters, $medicalServices, $laboratoryTests);
        } finally {
            config(['broadcasting.default' => $broadcastDriver]);
        }
    }

    private function seedRolesAndUsers(): void
    {
        $role = Role::query()->firstOrCreate([
            'name' => 'registration-supervisor',
            'guard_name' => 'web',
        ]);

        $role->syncPermissions([
            'view dashboard',
            'view counter management',
            'create counter management',
            'edit counter management',
            'view section management',
            'view patient management',
            'create patient management',
            'edit patient management',
            'view visit registration',
            'create visit registration',
            'edit visit registration',
            'view queue management',
            'edit queue management',
            'view doctor management',
            'view doctor schedule',
            'create doctor schedule',
            'edit doctor schedule',
            'view vital sign management',
            'create vital sign management',
            'edit vital sign management',
            'view medical record management',
            'create medical record management',
            'edit medical record management',
            'view icd10 management',
        ]);

        $users = [
            ['name' => 'CSI Super Admin', 'email' => 'superadmin@csi.local', 'role' => 'super-admin'],
            ['name' => 'CSI Clinic Admin', 'email' => 'clinicadmin@csi.local', 'role' => 'clinic-admin'],
            ['name' => 'Front Office Main', 'email' => 'frontoffice.main@csi.local', 'role' => 'front-office'],
            ['name' => 'Cashier Main', 'email' => 'cashier.main@csi.local', 'role' => 'cashier'],
            ['name' => 'Nurse General', 'email' => 'nurse.general@csi.local', 'role' => 'nurse'],
            ['name' => 'Doctor Maria', 'email' => 'doctor.maria@csi.local', 'role' => 'doctor'],
            ['name' => 'Pharmacist Main', 'email' => 'pharmacist.main@csi.local', 'role' => 'pharmacist'],
            ['name' => 'Registration Supervisor', 'email' => 'ops.supervisor@csi.local', 'role' => 'registration-supervisor'],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$userData['role']]);
        }

        $this->assignOwnerRoleForLocalTesting();
    }

    private function assignOwnerRoleForLocalTesting(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $owner = User::query()
            ->whereDoesntHave('roles')
            ->orderBy('id')
            ->first();

        if (! $owner) {
            return;
        }

        $owner->syncRoles(['super-admin']);
    }

    private function seedClinicAndBranches(): Clinic
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

        $branches = [
            [
                'code' => 'MAIN',
                'name' => 'Cabang Utama',
                'phone' => '021-555-0101',
                'address' => 'Jl. Operasional Klinik No. 1, Jakarta',
                'opening_time' => '08:00',
                'closing_time' => '20:00',
                'queue_prefix' => 'A',
                'queue_number_padding' => 3,
            ],
            [
                'code' => 'BDG',
                'name' => 'Cabang Bandung',
                'phone' => '022-555-0102',
                'address' => 'Jl. Asia Afrika No. 10, Bandung',
                'opening_time' => '08:00',
                'closing_time' => '19:00',
                'queue_prefix' => 'B',
                'queue_number_padding' => 3,
            ],
            [
                'code' => 'MDN',
                'name' => 'Cabang Medan',
                'phone' => '061-555-0103',
                'address' => 'Jl. Gatot Subroto No. 8, Medan',
                'opening_time' => '09:00',
                'closing_time' => '20:00',
                'queue_prefix' => 'M',
                'queue_number_padding' => 3,
            ],
        ];

        foreach ($branches as $branchData) {
            $clinic->branches()->updateOrCreate(
                ['code' => $branchData['code']],
                $branchData + ['is_active' => true],
            );
        }

        return $clinic;
    }

    private function seedSections(Clinic $clinic): array
    {
        $sectionMap = [];

        foreach ($clinic->branches as $branch) {
            $sectionDefinitions = match ($branch->code) {
                'MAIN' => [
                    ['code' => 'GENERAL', 'name' => 'General', 'type' => 'regular', 'queue_prefix' => 'GEN', 'sort_order' => 10],
                    ['code' => 'DENTAL', 'name' => 'Dental', 'type' => 'regular', 'queue_prefix' => 'DEN', 'sort_order' => 20],
                    ['code' => 'EMERGENCY', 'name' => 'Emergency', 'type' => 'emergency', 'queue_prefix' => 'IGD', 'sort_order' => 30],
                ],
                'BDG' => [
                    ['code' => 'GENERAL', 'name' => 'General', 'type' => 'regular', 'queue_prefix' => 'GEN', 'sort_order' => 10],
                    ['code' => 'DENTAL', 'name' => 'Dental', 'type' => 'regular', 'queue_prefix' => 'DEN', 'sort_order' => 20],
                ],
                default => [
                    ['code' => 'GENERAL', 'name' => 'General', 'type' => 'regular', 'queue_prefix' => 'GEN', 'sort_order' => 10],
                    ['code' => 'EMERGENCY', 'name' => 'Emergency', 'type' => 'emergency', 'queue_prefix' => 'IGD', 'sort_order' => 20],
                ],
            };

            foreach ($sectionDefinitions as $sectionData) {
                $section = Section::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'code' => $sectionData['code'],
                    ],
                    [
                        'name' => $sectionData['name'],
                        'type' => $sectionData['type'],
                        'queue_prefix' => $sectionData['queue_prefix'],
                        'queue_number_padding' => 3,
                        'allow_appointment' => $sectionData['type'] !== 'emergency',
                        'allow_walk_in' => true,
                        'description' => $sectionData['name'] . ' service at ' . $branch->name,
                        'sort_order' => $sectionData['sort_order'],
                        'is_active' => true,
                    ],
                );

                $sectionMap[$branch->code . ':' . $section->code] = $section;
            }
        }

        return $sectionMap;
    }

    private function seedCounters(Clinic $clinic): void
    {
        foreach ($clinic->branches as $branch) {
            $counters = [
                ['code' => 'CTR1', 'name' => 'Counter 1', 'location' => 'Lobby depan', 'sort_order' => 10],
                ['code' => 'CTR2', 'name' => 'Counter 2', 'location' => 'Lobby samping', 'sort_order' => 20],
            ];

            foreach ($counters as $counterData) {
                Counter::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'code' => $counterData['code'],
                    ],
                    [
                        'name' => $counterData['name'],
                        'location' => $counterData['location'],
                        'description' => 'Counter layanan untuk ' . $branch->name,
                        'sort_order' => $counterData['sort_order'],
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function seedDoctorsAndSchedules(Clinic $clinic, array $sections): void
    {
        $doctorDefinitions = [
            [
                'email' => 'maria.simanjuntak@example.com',
                'title_prefix' => 'dr.',
                'full_name' => 'Maria Simanjuntak',
                'title_suffix' => 'Sp.PD',
                'specialization' => 'Internal Medicine',
                'consultation_fee' => 200000,
                'str_number' => 'STR-200',
                'str_expired_at' => '2029-12-31',
                'sip_number' => 'SIP-200',
                'sip_expired_at' => '2028-12-31',
                'phone' => '081234567891',
                'address' => 'Jakarta',
                'section_keys' => ['MAIN:GENERAL', 'MDN:GENERAL'],
                'schedules' => [
                    ['branch' => 'MAIN', 'section' => 'GENERAL', 'day' => 1, 'start' => '08:00', 'end' => '12:00', 'slot' => 15, 'max' => 16, 'notes' => 'Morning clinic'],
                    ['branch' => 'MAIN', 'section' => 'GENERAL', 'day' => 3, 'start' => '13:00', 'end' => '17:00', 'slot' => 20, 'max' => 12, 'notes' => 'Afternoon clinic'],
                ],
                'leave' => [
                    'branch' => 'MAIN',
                    'leave_date' => now()->addDays(5)->format('Y-m-d'),
                    'leave_type' => 'partial_time',
                    'start_time' => '10:00',
                    'end_time' => '12:00',
                    'notes' => 'Seminar internal medicine',
                ],
            ],
            [
                'email' => 'budi.hartono@example.com',
                'title_prefix' => 'drg.',
                'full_name' => 'Budi Hartono',
                'title_suffix' => null,
                'specialization' => 'Dentist',
                'consultation_fee' => 175000,
                'str_number' => 'STR-201',
                'str_expired_at' => '2029-10-31',
                'sip_number' => 'SIP-201',
                'sip_expired_at' => '2028-10-31',
                'phone' => '081234567892',
                'address' => 'Bandung',
                'section_keys' => ['MAIN:DENTAL', 'BDG:DENTAL'],
                'schedules' => [
                    ['branch' => 'MAIN', 'section' => 'DENTAL', 'day' => 2, 'start' => '09:00', 'end' => '13:00', 'slot' => 20, 'max' => 10, 'notes' => 'Dental clinic'],
                    ['branch' => 'BDG', 'section' => 'DENTAL', 'day' => 4, 'start' => '10:00', 'end' => '14:00', 'slot' => 20, 'max' => 10, 'notes' => 'Bandung dental clinic'],
                ],
            ],
            [
                'email' => 'anita.siregar@example.com',
                'title_prefix' => 'dr.',
                'full_name' => 'Anita Siregar',
                'title_suffix' => null,
                'specialization' => 'General Practitioner',
                'consultation_fee' => 150000,
                'str_number' => 'STR-202',
                'str_expired_at' => '2030-01-31',
                'sip_number' => 'SIP-202',
                'sip_expired_at' => '2029-01-31',
                'phone' => '081234567893',
                'address' => 'Medan',
                'section_keys' => ['BDG:GENERAL', 'MDN:GENERAL'],
                'schedules' => [
                    ['branch' => 'BDG', 'section' => 'GENERAL', 'day' => 5, 'start' => '08:00', 'end' => '12:00', 'slot' => 15, 'max' => 18, 'notes' => 'General consultation'],
                    ['branch' => 'MDN', 'section' => 'GENERAL', 'day' => 6, 'start' => '09:00', 'end' => '13:00', 'slot' => 15, 'max' => 16, 'notes' => 'Weekend practice'],
                ],
            ],
        ];

        foreach ($doctorDefinitions as $doctorData) {
            $doctor = Doctor::query()->updateOrCreate(
                ['email' => $doctorData['email']],
                [
                    'title_prefix' => $doctorData['title_prefix'],
                    'full_name' => $doctorData['full_name'],
                    'title_suffix' => $doctorData['title_suffix'],
                    'specialization' => $doctorData['specialization'],
                    'consultation_fee' => $doctorData['consultation_fee'],
                    'str_number' => $doctorData['str_number'],
                    'str_expired_at' => $doctorData['str_expired_at'],
                    'sip_number' => $doctorData['sip_number'],
                    'sip_expired_at' => $doctorData['sip_expired_at'],
                    'phone' => $doctorData['phone'],
                    'address' => $doctorData['address'],
                    'is_active' => true,
                ],
            );

            $doctor->sections()->sync(collect($doctorData['section_keys'])
                ->map(fn (string $key) => $sections[$key]->id ?? null)
                ->filter()
                ->values()
                ->all());

            foreach ($doctorData['schedules'] as $scheduleData) {
                $section = $sections[$scheduleData['branch'] . ':' . $scheduleData['section']] ?? null;
                $branch = $clinic->branches->firstWhere('code', $scheduleData['branch']);

                if (! $section || ! $branch) {
                    continue;
                }

                DoctorSchedule::query()->updateOrCreate(
                    [
                        'doctor_id' => $doctor->id,
                        'branch_id' => $branch->id,
                        'section_id' => $section->id,
                        'day_of_week' => $scheduleData['day'],
                        'start_time' => $scheduleData['start'],
                    ],
                    [
                        'end_time' => $scheduleData['end'],
                        'slot_duration_minutes' => $scheduleData['slot'],
                        'max_patients' => $scheduleData['max'],
                        'notes' => $scheduleData['notes'],
                        'is_active' => true,
                    ],
                );
            }

            if (isset($doctorData['leave'])) {
                $branch = $clinic->branches->firstWhere('code', $doctorData['leave']['branch']);

                if ($branch) {
                    DoctorLeave::query()->updateOrCreate(
                        [
                            'doctor_id' => $doctor->id,
                            'branch_id' => $branch->id,
                            'leave_date' => $doctorData['leave']['leave_date'],
                        ],
                        [
                            'leave_type' => $doctorData['leave']['leave_type'],
                            'start_time' => $doctorData['leave']['start_time'],
                            'end_time' => $doctorData['leave']['end_time'],
                            'notes' => $doctorData['leave']['notes'],
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    private function seedPatients(Clinic $clinic): array
    {
        $faker = fake('id_ID');
        $recordService = app(PatientRecordService::class);
        $patients = [];
        $branches = $clinic->branches->values();
        $regions = [
            ['province' => ['32', 'Jawa Barat'], 'city' => ['3273', 'Kota Bandung'], 'district' => ['327301', 'Coblong'], 'village' => ['3273011001', 'Dago']],
            ['province' => ['12', 'Sumatera Utara'], 'city' => ['1275', 'Kota Medan'], 'district' => ['127507', 'Medan Petisah'], 'village' => ['1275071002', 'Sei Putih Barat']],
            ['province' => ['31', 'DKI Jakarta'], 'city' => ['3173', 'Kota Jakarta Barat'], 'district' => ['317304', 'Palmerah'], 'village' => ['3173041001', 'Slipi']],
        ];

        for ($index = 1; $index <= 50; $index++) {
            $region = $regions[($index - 1) % count($regions)];
            $phone = sprintf('0812300%05d', $index);
            $nik = $index % 7 === 0 ? null : sprintf('317401%010d', $index);

            $patient = Patient::query()->updateOrCreate(
                ['phone' => $phone],
                [
                    'full_name' => $faker->name(),
                    'gender' => $index % 2 === 0 ? 'male' : 'female',
                    'date_of_birth' => Carbon::now()->subYears(rand(1, 70))->subDays(rand(0, 364))->toDateString(),
                    'nik' => $nik,
                    'email' => "patient{$index}@csi.local",
                    'province_code' => $region['province'][0],
                    'province_name' => $region['province'][1],
                    'city_code' => $region['city'][0],
                    'city_name' => $region['city'][1],
                    'district_code' => $region['district'][0],
                    'district_name' => $region['district'][1],
                    'village_code' => $region['village'][0],
                    'village_name' => $region['village'][1],
                    'address_line' => $faker->streetAddress(),
                    'allergy_notes' => $index % 6 === 0 ? 'Alergi penicillin' : null,
                    'is_active' => true,
                ],
            );

            $primaryBranch = $branches[$index % $branches->count()];
            $recordService->ensureBranchRecord($patient, $primaryBranch);

            if ($index % 3 === 0) {
                $secondaryBranch = $branches[($index + 1) % $branches->count()];
                $recordService->ensureBranchRecord($patient, $secondaryBranch);
            }

            $patients[] = $patient->fresh(['branchRecords']);
        }

        return $patients;
    }

    private function seedIcd10Codes(): array
    {
        $definitions = [
            ['chapter' => 'I', 'code' => 'A09', 'en' => 'Infectious gastroenteritis and colitis, unspecified', 'id' => 'Gastroenteritis dan kolitis infeksi, tidak spesifik'],
            ['chapter' => 'I', 'code' => 'A90', 'en' => 'Dengue fever [classical dengue]', 'id' => 'Demam dengue'],
            ['chapter' => 'I', 'code' => 'B34.9', 'en' => 'Viral infection, unspecified', 'id' => 'Infeksi virus, tidak spesifik'],
            ['chapter' => 'X', 'code' => 'J06.9', 'en' => 'Acute upper respiratory infection, unspecified', 'id' => 'Infeksi saluran napas atas akut, tidak spesifik'],
            ['chapter' => 'X', 'code' => 'J18.9', 'en' => 'Pneumonia, unspecified organism', 'id' => 'Pneumonia, organisme tidak spesifik'],
            ['chapter' => 'X', 'code' => 'J20.9', 'en' => 'Acute bronchitis, unspecified', 'id' => 'Bronkitis akut, tidak spesifik'],
            ['chapter' => 'X', 'code' => 'J45.9', 'en' => 'Asthma, unspecified', 'id' => 'Asma, tidak spesifik'],
            ['chapter' => 'XI', 'code' => 'K02.9', 'en' => 'Dental caries, unspecified', 'id' => 'Karies gigi, tidak spesifik'],
            ['chapter' => 'XI', 'code' => 'K30', 'en' => 'Functional dyspepsia', 'id' => 'Dispepsia fungsional'],
            ['chapter' => 'XI', 'code' => 'K52.9', 'en' => 'Noninfective gastroenteritis and colitis, unspecified', 'id' => 'Gastroenteritis dan kolitis noninfeksi, tidak spesifik'],
            ['chapter' => 'XIV', 'code' => 'N39.0', 'en' => 'Urinary tract infection, site not specified', 'id' => 'Infeksi saluran kemih, lokasi tidak spesifik'],
            ['chapter' => 'XIV', 'code' => 'N76.0', 'en' => 'Acute vaginitis', 'id' => 'Vaginitis akut'],
            ['chapter' => 'XVIII', 'code' => 'R05', 'en' => 'Cough', 'id' => 'Batuk'],
            ['chapter' => 'XVIII', 'code' => 'R50.9', 'en' => 'Fever, unspecified', 'id' => 'Demam, tidak spesifik'],
            ['chapter' => 'XVIII', 'code' => 'R51', 'en' => 'Headache', 'id' => 'Sakit kepala'],
            ['chapter' => 'IX', 'code' => 'I10', 'en' => 'Essential (primary) hypertension', 'id' => 'Hipertensi esensial primer'],
            ['chapter' => 'IV', 'code' => 'E11.9', 'en' => 'Type 2 diabetes mellitus without complications', 'id' => 'Diabetes melitus tipe 2 tanpa komplikasi'],
            ['chapter' => 'XIII', 'code' => 'M54.5', 'en' => 'Low back pain', 'id' => 'Nyeri punggung bawah'],
            ['chapter' => 'XIII', 'code' => 'M79.1', 'en' => 'Myalgia', 'id' => 'Mialgia'],
            ['chapter' => 'XII', 'code' => 'L30.9', 'en' => 'Dermatitis, unspecified', 'id' => 'Dermatitis, tidak spesifik'],
            ['chapter' => 'VI', 'code' => 'G44.2', 'en' => 'Tension-type headache', 'id' => 'Sakit kepala tipe tegang'],
            ['chapter' => 'VII', 'code' => 'H10.9', 'en' => 'Conjunctivitis, unspecified', 'id' => 'Konjungtivitis, tidak spesifik'],
        ];

        $codes = [];

        foreach ($definitions as $definition) {
            $code = Icd10Code::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'chapter_code' => $definition['chapter'],
                    'name_en' => $definition['en'],
                    'name_id' => $definition['id'],
                    'description' => 'WHO ICD-10 starter subset for CSI demo seed.',
                    'is_active' => true,
                ],
            );

            $codes[$definition['code']] = $code;
        }

        return $codes;
    }

    private function seedPharmacyFoundation(Clinic $clinic): array
    {
        $definitions = [
            ['code' => 'PCM500', 'name' => 'Paracetamol', 'generic' => 'Paracetamol', 'ingredients' => 'Paracetamol', 'allergy_keywords' => 'paracetamol, acetaminophen', 'therapy_class' => 'analgesic-antipyretic', 'contra' => 'Use caution in severe liver disease.', 'form' => 'tablet', 'strength' => '500 mg', 'unit' => 'tablet', 'compoundable' => true, 'price' => 1200],
            ['code' => 'AMX500', 'name' => 'Amoxicillin', 'generic' => 'Amoxicillin', 'ingredients' => 'Amoxicillin', 'allergy_keywords' => 'amoxicillin, penicillin, beta-lactam', 'therapy_class' => 'beta-lactam antibiotic', 'contra' => 'Avoid in patients with known penicillin allergy.', 'form' => 'capsule', 'strength' => '500 mg', 'unit' => 'capsule', 'compoundable' => true, 'price' => 2500],
            ['code' => 'CTM4', 'name' => 'CTM', 'generic' => 'Chlorpheniramine Maleate', 'ingredients' => 'Chlorpheniramine Maleate', 'allergy_keywords' => 'chlorpheniramine, antihistamine', 'therapy_class' => 'antihistamine', 'contra' => 'May cause drowsiness; caution for patients operating vehicles.', 'form' => 'tablet', 'strength' => '4 mg', 'unit' => 'tablet', 'compoundable' => true, 'price' => 900],
            ['code' => 'OMZ20', 'name' => 'Omeprazole', 'generic' => 'Omeprazole', 'ingredients' => 'Omeprazole', 'allergy_keywords' => 'omeprazole, proton pump inhibitor', 'therapy_class' => 'proton pump inhibitor', 'contra' => 'Review prolonged use and gastric alarm symptoms.', 'form' => 'capsule', 'strength' => '20 mg', 'unit' => 'capsule', 'compoundable' => false, 'price' => 3200],
            ['code' => 'ALB60', 'name' => 'Salbutamol Syrup', 'generic' => 'Salbutamol', 'ingredients' => 'Salbutamol', 'allergy_keywords' => 'salbutamol, albuterol', 'therapy_class' => 'bronchodilator', 'contra' => 'Monitor heart rate in pediatric patients.', 'form' => 'syrup', 'strength' => '2 mg/5 mL', 'unit' => 'bottle', 'compoundable' => false, 'price' => 18500],
        ];

        $medicines = [];

        foreach ($definitions as $definition) {
            $medicine = Medicine::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'generic_name' => $definition['generic'],
                    'active_ingredients' => $definition['ingredients'],
                    'allergy_keywords' => $definition['allergy_keywords'],
                    'dosage_form' => $definition['form'],
                    'therapeutic_class' => $definition['therapy_class'],
                    'strength' => $definition['strength'],
                    'base_unit' => $definition['unit'],
                    'description' => 'Demo pharmacy item for CSI clinic.',
                    'contraindication_notes' => $definition['contra'],
                    'is_compoundable' => $definition['compoundable'],
                    'is_active' => true,
                ],
            );

            foreach ($clinic->branches as $branch) {
                $medicine->branchPrices()->updateOrCreate(
                    ['branch_id' => $branch->id],
                    [
                        'selling_price' => $definition['price'] + ($branch->code === 'BDG' ? 250 : 0) + ($branch->code === 'MDN' ? 500 : 0),
                        'is_active' => true,
                    ],
                );

                MedicineBatch::query()->updateOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'medicine_id' => $medicine->id,
                        'batch_number' => $definition['code'] . '-' . $branch->code . '-A1',
                    ],
                    [
                        'received_at' => now()->subDays(14)->toDateString(),
                        'expired_at' => now()->addMonths(10)->toDateString(),
                        'quantity_received' => 250,
                        'quantity_available' => 250,
                        'purchase_cost' => $definition['price'] * 0.55,
                        'supplier_name' => 'PT Demo Farmasi',
                        'notes' => 'Seed demo batch.',
                        'is_active' => true,
                    ],
                );
            }

            $medicines[$definition['code']] = $medicine->fresh(['branchPrices', 'batches']);
        }

        return $medicines;
    }

    private function seedProcedureMasters(Clinic $clinic): array
    {
        $definitions = [
            ['code' => 'NEB', 'name' => 'Nebulizer', 'scope' => 'both', 'requires_doctor_order' => true, 'fee' => 90000],
            ['code' => 'DRESSING', 'name' => 'Wound Dressing', 'scope' => 'nurse_only', 'requires_doctor_order' => true, 'fee' => 75000],
            ['code' => 'INJECTION', 'name' => 'Injection', 'scope' => 'both', 'requires_doctor_order' => true, 'fee' => 50000],
        ];

        $masters = [];

        foreach ($definitions as $definition) {
            $master = ProcedureMaster::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'description' => 'Seed demo procedure.',
                    'performer_scope' => $definition['scope'],
                    'requires_doctor_order' => $definition['requires_doctor_order'],
                    'default_fee' => $definition['fee'],
                    'is_active' => true,
                ],
            );

            foreach ($clinic->branches as $branch) {
                $master->branchPrices()->updateOrCreate(
                    ['branch_id' => $branch->id],
                    [
                        'price' => $definition['fee'] + ($branch->code === 'MDN' ? 5000 : 0),
                        'is_active' => true,
                    ],
                );
            }

            $masters[$definition['code']] = $master->fresh(['branchPrices']);
        }

        return $masters;
    }

    private function seedMedicalServices(Clinic $clinic): array
    {
        $definitions = [
            ['code' => 'OBS', 'name' => 'Observation Room', 'type' => 'observation', 'fee' => 120000],
            ['code' => 'NURSE-FEE', 'name' => 'Nurse Service', 'type' => 'nursing', 'fee' => 45000],
            ['code' => 'ADMIN-CLINIC', 'name' => 'Clinical Admin Fee', 'type' => 'administrative', 'fee' => 30000],
        ];

        $services = [];

        foreach ($definitions as $definition) {
            $service = MedicalService::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'service_type' => $definition['type'],
                    'description' => 'Seed demo medical service.',
                    'default_fee' => $definition['fee'],
                    'is_active' => true,
                ],
            );

            foreach ($clinic->branches as $branch) {
                $service->branchPrices()->updateOrCreate(
                    ['branch_id' => $branch->id],
                    [
                        'price' => $definition['fee'] + ($branch->code === 'MDN' ? 5000 : 0),
                        'is_active' => true,
                    ],
                );
            }

            $services[$definition['code']] = $service->fresh(['branchPrices']);
        }

        return $services;
    }

    private function seedLaboratoryTests(Clinic $clinic): array
    {
        $definitions = [
            [
                'code' => 'CBC',
                'name' => 'Complete Blood Count',
                'category' => 'laboratory',
                'sample_type' => 'Whole blood',
                'provider' => 'internal',
                'result_mode' => 'structured',
                'parameters' => [
                    ['code' => 'HB', 'name' => 'Hb', 'unit' => 'g/dL', 'range' => '12 - 16'],
                    ['code' => 'WBC', 'name' => 'Leukocyte', 'unit' => '10^3/uL', 'range' => '4 - 10'],
                ],
                'internal_price' => 85000,
                'external_price' => 95000,
            ],
            [
                'code' => 'GLU',
                'name' => 'Blood Glucose',
                'category' => 'laboratory',
                'sample_type' => 'Serum',
                'provider' => 'internal',
                'result_mode' => 'structured',
                'parameters' => [
                    ['code' => 'GLU', 'name' => 'Glucose', 'unit' => 'mg/dL', 'range' => '70 - 140'],
                ],
                'internal_price' => 50000,
                'external_price' => 65000,
            ],
            [
                'code' => 'CXR',
                'name' => 'Chest X-Ray',
                'category' => 'radiology',
                'sample_type' => null,
                'provider' => 'external',
                'result_mode' => 'narrative',
                'parameters' => [],
                'internal_price' => 0,
                'external_price' => 175000,
            ],
        ];

        $tests = [];

        foreach ($definitions as $definition) {
            $test = LaboratoryTest::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'diagnostic_category' => $definition['category'],
                    'sample_type' => $definition['sample_type'],
                    'default_provider_type' => $definition['provider'],
                    'result_entry_mode' => $definition['result_mode'],
                    'description' => 'Seed demo laboratory test.',
                    'is_active' => true,
                ],
            );

            $test->parameters()->delete();
            $test->parameters()->createMany(
                collect($definition['parameters'])->map(fn ($parameter, $index) => [
                    'code' => $parameter['code'],
                    'name' => $parameter['name'],
                    'unit' => $parameter['unit'],
                    'reference_range' => $parameter['range'],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                ])->all()
            );

            foreach ($clinic->branches as $branch) {
                $test->branchPrices()->updateOrCreate(
                    ['branch_id' => $branch->id],
                    [
                        'internal_price' => $definition['internal_price'],
                        'external_price' => $definition['external_price'],
                        'is_active' => true,
                    ],
                );
            }

            $tests[$definition['code']] = $test->fresh(['parameters', 'branchPrices']);
        }

        return $tests;
    }

    private function seedVisitRegistrationsAndQueues(Clinic $clinic, array $sections, array $patients): array
    {
        $queueService = app(QueueService::class);
        $recordService = app(PatientRecordService::class);
        $doctorAssignmentService = app(VisitDoctorAssignmentService::class);
        $today = Carbon::today();
        $registrations = [];
        $todaySchedules = DoctorSchedule::query()
            ->with(['doctor', 'section', 'branch'])
            ->where('day_of_week', $today->dayOfWeekIso)
            ->where('is_active', true)
            ->get();

        if ($todaySchedules->isEmpty()) {
            $todaySchedules = DoctorSchedule::query()
                ->with(['doctor', 'section', 'branch'])
                ->where('is_active', true)
                ->orderBy('doctor_id')
                ->orderBy('start_time')
                ->limit(2)
                ->get();
        }

        $seedTarget = min(5, count($patients));
        $seededCount = 0;

        foreach ($todaySchedules as $schedule) {
            $counter = Counter::query()->where('branch_id', $schedule->branch_id)->orderBy('sort_order')->first();

            if (! $counter) {
                continue;
            }

            $availableSlots = max(1, min(5, (int) $schedule->max_patients));

            for ($slotIndex = 0; $slotIndex < $availableSlots && $seededCount < $seedTarget; $slotIndex++) {
                $patient = $patients[$seededCount] ?? null;

                if (! $patient) {
                    break;
                }

                $record = $recordService->ensureBranchRecord($patient, $schedule->branch);
                $slotStart = Carbon::parse($today->toDateString() . ' ' . $schedule->start_time)
                    ->addMinutes($schedule->slot_duration_minutes * $slotIndex);
                $slotEnd = $slotStart->copy()->addMinutes($schedule->slot_duration_minutes);

                if ($slotEnd->format('H:i:s') > $schedule->end_time) {
                    continue;
                }

                $registration = VisitRegistration::query()->updateOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'visit_date' => $today->toDateString(),
                        'visit_type' => 'same_day',
                        'section_id' => $schedule->section_id,
                    ],
                    [
                        'patient_branch_record_id' => $record->id,
                        'branch_id' => $schedule->branch_id,
                        'counter_id' => $counter->id,
                        'doctor_id' => $schedule->doctor_id,
                        'doctor_schedule_id' => $schedule->id,
                        'registration_status' => 'queued',
                        'care_stage' => 'waiting_nurse',
                        'vital_status' => 'pending',
                        'booking_code' => null,
                        'slot_start_time' => $slotStart->format('H:i:s'),
                        'slot_end_time' => $slotEnd->format('H:i:s'),
                        'notes' => 'Demo same day visit',
                        'checked_in_at' => now(),
                    ],
                );

                $doctorAssignmentService->assign(
                    $registration->fresh(['doctorAssignments', 'queueTicket', 'doctor']),
                    $registration->doctor,
                    'Initial assignment from demo seed.',
                );

                $queueTicket = $queueService->createForRegistration($registration->fresh());
                $this->applyQueueStatusDemo($queueTicket, $seededCount);
                $registrations[] = $registration->fresh(['section', 'doctor', 'patient', 'patientBranchRecord']);
                $seededCount++;
            }

            if ($seededCount >= $seedTarget) {
                break;
            }
        }

        $emergencySection = collect($sections)
            ->first(fn (Section $section): bool => $section->type === 'emergency');

        if ($emergencySection) {
            $counter = Counter::query()->where('branch_id', $emergencySection->branch_id)->orderBy('sort_order')->first();
            $patient = $patients[10] ?? null;

            if ($counter && $patient) {
                $record = $recordService->ensureBranchRecord($patient, $emergencySection->branch);
                $registration = VisitRegistration::query()->updateOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'visit_date' => $today->toDateString(),
                        'visit_type' => 'emergency',
                        'section_id' => $emergencySection->id,
                    ],
                    [
                        'patient_branch_record_id' => $record->id,
                        'branch_id' => $emergencySection->branch_id,
                        'counter_id' => $counter->id,
                        'doctor_id' => null,
                        'doctor_schedule_id' => null,
                        'registration_status' => 'queued',
                        'care_stage' => 'waiting_doctor',
                        'vital_status' => 'pending',
                        'booking_code' => null,
                        'slot_start_time' => null,
                        'slot_end_time' => null,
                        'notes' => 'Demo emergency visit',
                        'checked_in_at' => now(),
                    ],
                );

                $queueService->createForRegistration($registration->fresh());
                $registrations[] = $registration->fresh(['section', 'doctor', 'patient', 'patientBranchRecord']);
            }
        }

        $bookingSchedules = DoctorSchedule::query()
            ->with(['doctor', 'section', 'branch'])
            ->where('is_active', true)
            ->take(4)
            ->get();

        foreach ($bookingSchedules as $index => $schedule) {
            $patient = $patients[20 + $index] ?? null;
            $counter = Counter::query()->where('branch_id', $schedule->branch_id)->orderBy('sort_order')->first();

            if (! $patient || ! $counter) {
                continue;
            }

            $visitDate = Carbon::today()->next($schedule->day_of_week);
            $record = $recordService->ensureBranchRecord($patient, $schedule->branch);
            $slotStart = Carbon::parse($visitDate->toDateString() . ' ' . $schedule->start_time);
            $slotEnd = $slotStart->copy()->addMinutes($schedule->slot_duration_minutes);

            VisitRegistration::query()->updateOrCreate(
                [
                    'patient_id' => $patient->id,
                    'visit_date' => $visitDate->toDateString(),
                    'visit_type' => 'booking',
                    'section_id' => $schedule->section_id,
                ],
                [
                    'patient_branch_record_id' => $record->id,
                    'branch_id' => $schedule->branch_id,
                    'counter_id' => $counter->id,
                    'doctor_id' => $schedule->doctor_id,
                    'doctor_schedule_id' => $schedule->id,
                    'registration_status' => 'booked',
                    'care_stage' => 'scheduled',
                    'vital_status' => 'pending',
                    'booking_code' => sprintf('BK-%s-%04d', $visitDate->format('Ymd'), $index + 1),
                    'slot_start_time' => $slotStart->format('H:i:s'),
                    'slot_end_time' => $slotEnd->format('H:i:s'),
                    'notes' => 'Demo booking visit',
                ],
            );
        }

        return $registrations;
    }

    private function seedClinicalDocumentation(array $registrations, array $icd10Codes): void
    {
        $nurse = User::query()->where('email', 'nurse.general@csi.local')->first();
        $admin = User::query()->where('email', 'clinicadmin@csi.local')->first();

        foreach (collect($registrations)->values() as $index => $registration) {
            $recordedAt = now()->subMinutes(90 - ($index * 7));

            VitalSignRecord::query()->updateOrCreate(
                [
                    'visit_registration_id' => $registration->id,
                    'recorded_at' => $recordedAt,
                ],
                [
                    'patient_id' => $registration->patient_id,
                    'branch_id' => $registration->branch_id,
                    'section_id' => $registration->section_id,
                    'recorded_by_user_id' => $nurse?->id,
                    'systolic_bp' => 110 + ($index * 3),
                    'diastolic_bp' => 70 + ($index * 2),
                    'temperature_celsius' => 36.5 + (($index % 3) * 0.3),
                    'pulse_rate' => 78 + ($index * 2),
                    'respiratory_rate' => 18 + ($index % 4),
                    'weight_kg' => 52 + ($index * 1.7),
                    'height_cm' => 154 + ($index * 1.5),
                    'spo2_percent' => 97 - ($index % 2),
                    'bmi' => 21.4 + ($index * 0.4),
                    'notes' => $registration->visit_type === 'emergency'
                        ? 'Vital signs after initial stabilization.'
                        : 'Vital signs routine sebelum konsultasi dokter.',
                ],
            );

            $registration->update([
                'vital_status' => 'completed',
                'care_stage' => 'waiting_doctor',
            ]);
        }

        foreach (collect($registrations)->take(4)->values() as $index => $registration) {
            $doctor = $registration->doctor ?: $registration->section?->doctors()->where('is_active', true)->first();

            if (! $doctor) {
                continue;
            }

            $record = MedicalRecord::query()->updateOrCreate(
                ['visit_registration_id' => $registration->id],
                [
                    'patient_id' => $registration->patient_id,
                    'branch_id' => $registration->branch_id,
                    'section_id' => $registration->section_id,
                    'doctor_id' => $doctor->id,
                    'subjective' => match ($index) {
                        0 => 'Pasien mengeluh batuk pilek sejak 3 hari, tanpa sesak.',
                        1 => 'Pasien demam disertai nyeri kepala dan mual sejak kemarin.',
                        2 => 'Pasien nyeri ulu hati dan begah setelah makan.',
                        default => 'Pasien datang dengan keluhan nyeri mendadak dan lemas.',
                    },
                    'objective' => 'Keadaan umum cukup. Temuan fisik didukung vital signs terbaru dan pemeriksaan fokus sesuai keluhan.',
                    'assessment' => match ($index) {
                        0 => 'Infeksi saluran napas atas akut, pertimbangkan terapi simptomatik.',
                        1 => 'Demam akut, perlu observasi hidrasi dan evaluasi infeksi virus.',
                        2 => 'Dispepsia fungsional dengan gejala dominan gastrointestinal atas.',
                        default => 'Kondisi akut dalam observasi, evaluasi lanjutan di area emergency.',
                    },
                    'plan' => match ($index) {
                        0 => 'Edukasi, terapi simptomatik, kontrol bila memburuk.',
                        1 => 'Terapi suportif, monitoring suhu, evaluasi laboratorium bila perlu.',
                        2 => 'Terapi lambung, modifikasi pola makan, follow up 3 hari.',
                        default => 'Stabilisasi awal, monitoring ketat, reassessment setelah observasi.',
                    },
                    'diagnosis_notes' => 'Demo SOAP seeded for CSI clinic workflow.',
                    'status' => $index === 2 ? 'reopen_requested' : ($index === 3 ? 'reopened' : 'final'),
                    'finalized_at' => now()->subMinutes(60 - ($index * 10)),
                    'finalized_by_user_id' => $admin?->id,
                    'reopen_requested_at' => $index === 2 ? now()->subMinutes(8) : null,
                    'reopen_requested_by_user_id' => $index === 2 ? $admin?->id : null,
                    'reopen_request_reason' => $index === 2 ? 'Perlu koreksi plan dan diagnosis sekunder.' : null,
                    'reopened_at' => $index === 3 ? now()->subMinutes(4) : null,
                    'reopened_by_user_id' => $index === 3 ? $admin?->id : null,
                    'reopen_approved_by_user_id' => $index === 3 ? $admin?->id : null,
                    'reopen_approval_reason' => $index === 3 ? 'Koreksi dokumentasi SOAP disetujui admin.' : null,
                ],
            );

            $record->diagnoses()->delete();

            $primaryCode = match ($index) {
                0 => $icd10Codes['J06.9'] ?? null,
                1 => $icd10Codes['R50.9'] ?? null,
                2 => $icd10Codes['K30'] ?? null,
                default => $icd10Codes['R50.9'] ?? null,
            };

            $secondaryCode = match ($index) {
                0 => $icd10Codes['R05'] ?? null,
                1 => $icd10Codes['A90'] ?? null,
                2 => $icd10Codes['A09'] ?? null,
                default => $icd10Codes['R51'] ?? null,
            };

            if ($primaryCode) {
                $record->diagnoses()->create([
                    'icd10_code_id' => $primaryCode->id,
                    'diagnosis_type' => 'primary',
                    'sort_order' => 1,
                ]);
            }

            if ($secondaryCode) {
                $record->diagnoses()->create([
                    'icd10_code_id' => $secondaryCode->id,
                    'diagnosis_type' => 'secondary',
                    'sort_order' => 1,
                ]);
            }

            $record->audits()->create([
                'action' => $record->status === 'final' ? 'finalized' : $record->status,
                'notes' => 'Demo audit trail for medical record module.',
                'snapshot' => [
                    'status' => $record->status,
                    'doctor_id' => $record->doctor_id,
                    'subjective' => $record->subjective,
                ],
                'performed_by_user_id' => $admin?->id,
            ]);

            $registration->update([
                'care_stage' => match ($record->status) {
                    'final' => 'ready_for_checkout',
                    'reopen_requested', 'reopened' => 'in_consultation',
                    default => 'waiting_doctor',
                },
            ]);
        }
    }

    private function seedReferralAndDoctorLettersDemo(array $registrations): void
    {
        $branchDocumentNumberService = app(BranchDocumentNumberService::class);
        $finalVisits = collect($registrations)
            ->filter(fn (VisitRegistration $visit): bool => $visit->medicalRecord?->status === 'final')
            ->values();

        $hospital = ReferralDestination::query()->updateOrCreate(
            ['code' => 'RS-ALPHA'],
            [
                'destination_type' => 'hospital',
                'name' => 'RS Alpha Medika',
                'address' => 'Jl. Referral No. 1, Jakarta',
                'contact_person' => 'Admission Desk',
                'phone' => '021-777-0101',
                'notes' => 'Demo destination hospital',
                'is_active' => true,
            ],
        );

        ReferralDestination::query()->updateOrCreate(
            ['code' => 'SPC-ENT'],
            [
                'destination_type' => 'specialist',
                'name' => 'Specialist ENT Center',
                'address' => 'Jl. Specialist No. 8, Bandung',
                'contact_person' => 'Dr. Andre Team',
                'phone' => '022-888-0101',
                'notes' => 'Demo destination specialist',
                'is_active' => true,
            ],
        );

        ReferralDestination::query()->updateOrCreate(
            ['code' => 'LAB-RAD'],
            [
                'destination_type' => 'lab_radiology',
                'name' => 'Partner Lab & Radiology',
                'address' => 'Jl. Diagnostic No. 3, Medan',
                'contact_person' => 'Referral Counter',
                'phone' => '061-999-0101',
                'notes' => 'Demo destination lab and radiology',
                'is_active' => true,
            ],
        );

        $issuedVisit = $finalVisits->get(0);
        $draftVisit = $finalVisits->get(1) ?? $issuedVisit;

        if ($issuedVisit) {
            PatientReferral::query()->updateOrCreate(
                [
                    'visit_registration_id' => $issuedVisit->id,
                    'destination_name' => $hospital->name,
                    'status' => 'issued',
                ],
                [
                    'patient_id' => $issuedVisit->patient_id,
                    'patient_branch_record_id' => $issuedVisit->patient_branch_record_id,
                    'branch_id' => $issuedVisit->branch_id,
                    'section_id' => $issuedVisit->section_id,
                    'doctor_id' => $issuedVisit->medicalRecord?->doctor_id ?: $issuedVisit->doctor_id,
                    'referral_destination_id' => $hospital->id,
                    'referral_no' => $branchDocumentNumberService->nextReferralNumber($issuedVisit->branch),
                    'destination_type' => $hospital->destination_type,
                    'destination_address' => $hospital->address,
                    'destination_phone' => $hospital->phone,
                    'diagnosis_summary' => 'J06.9 - Acute upper respiratory infection',
                    'clinical_summary' => 'Pasien memerlukan evaluasi lanjutan di rumah sakit rujukan.',
                    'treatment_summary' => 'Terapi simptomatik awal dan observasi.',
                    'reason' => 'Membutuhkan fasilitas penunjang lanjutan.',
                    'doctor_name_snapshot' => $issuedVisit->medicalRecord?->doctor?->displayName(),
                    'doctor_specialization_snapshot' => $issuedVisit->medicalRecord?->doctor?->specialization,
                    'doctor_signature_path_snapshot' => $issuedVisit->medicalRecord?->doctor?->signature_path,
                    'issued_by_user_id' => User::query()->where('email', 'clinicadmin@csi.local')->value('id'),
                    'issued_at' => now()->subDay(),
                ],
            );

            DoctorLetter::query()->updateOrCreate(
                [
                    'visit_registration_id' => $issuedVisit->id,
                    'letter_type' => 'sick_note',
                    'status' => 'issued',
                ],
                [
                    'patient_id' => $issuedVisit->patient_id,
                    'patient_branch_record_id' => $issuedVisit->patient_branch_record_id,
                    'branch_id' => $issuedVisit->branch_id,
                    'section_id' => $issuedVisit->section_id,
                    'doctor_id' => $issuedVisit->medicalRecord?->doctor_id ?: $issuedVisit->doctor_id,
                    'letter_no' => $branchDocumentNumberService->nextDoctorLetterNumber($issuedVisit->branch, 'sick_note'),
                    'issue_date' => now()->subDay()->toDateString(),
                    'diagnosis_summary' => 'Infeksi saluran napas atas akut.',
                    'sick_start_date' => now()->subDay()->toDateString(),
                    'sick_end_date' => now()->addDay()->toDateString(),
                    'sick_total_days' => 3,
                    'doctor_name_snapshot' => $issuedVisit->medicalRecord?->doctor?->displayName(),
                    'doctor_specialization_snapshot' => $issuedVisit->medicalRecord?->doctor?->specialization,
                    'doctor_signature_path_snapshot' => $issuedVisit->medicalRecord?->doctor?->signature_path,
                    'sip_number_snapshot' => $issuedVisit->medicalRecord?->doctor?->sip_number,
                    'issued_by_user_id' => User::query()->where('email', 'clinicadmin@csi.local')->value('id'),
                    'issued_at' => now()->subDay(),
                ],
            );

            DoctorLetter::query()->updateOrCreate(
                [
                    'visit_registration_id' => $issuedVisit->id,
                    'letter_type' => 'drug_free_note',
                    'status' => 'issued',
                ],
                [
                    'patient_id' => $issuedVisit->patient_id,
                    'patient_branch_record_id' => $issuedVisit->patient_branch_record_id,
                    'branch_id' => $issuedVisit->branch_id,
                    'section_id' => $issuedVisit->section_id,
                    'doctor_id' => $issuedVisit->medicalRecord?->doctor_id ?: $issuedVisit->doctor_id,
                    'letter_no' => $branchDocumentNumberService->nextDoctorLetterNumber($issuedVisit->branch, 'drug_free_note'),
                    'issue_date' => now()->subDay()->toDateString(),
                    'diagnosis_summary' => 'Administrative clearance.',
                    'drug_test_date' => now()->subDay()->toDateString(),
                    'drug_test_method' => 'Rapid test urine',
                    'drug_test_result' => 'Negatif',
                    'drug_free_statement' => 'Berdasarkan pemeriksaan klinis dan hasil screening yang tersedia, pasien dinyatakan bebas narkoba pada saat surat ini diterbitkan.',
                    'doctor_name_snapshot' => $issuedVisit->medicalRecord?->doctor?->displayName(),
                    'doctor_specialization_snapshot' => $issuedVisit->medicalRecord?->doctor?->specialization,
                    'doctor_signature_path_snapshot' => $issuedVisit->medicalRecord?->doctor?->signature_path,
                    'sip_number_snapshot' => $issuedVisit->medicalRecord?->doctor?->sip_number,
                    'issued_by_user_id' => User::query()->where('email', 'clinicadmin@csi.local')->value('id'),
                    'issued_at' => now()->subDay(),
                ],
            );
        }

        if ($draftVisit) {
            DoctorLetter::query()->updateOrCreate(
                [
                    'visit_registration_id' => $draftVisit->id,
                    'letter_type' => 'control_note',
                    'status' => 'draft',
                ],
                [
                    'patient_id' => $draftVisit->patient_id,
                    'patient_branch_record_id' => $draftVisit->patient_branch_record_id,
                    'branch_id' => $draftVisit->branch_id,
                    'section_id' => $draftVisit->section_id,
                    'doctor_id' => $draftVisit->medicalRecord?->doctor_id ?: $draftVisit->doctor_id,
                    'issue_date' => now()->toDateString(),
                    'diagnosis_summary' => 'Follow-up kontrol pasca terapi awal.',
                    'control_date' => now()->addDays(7)->toDateString(),
                    'control_notes' => 'Kontrol ulang sesuai evaluasi dokter.',
                ],
            );
        }
    }

    private function applyQueueStatusDemo(\App\Models\QueueTicket $queueTicket, int $index): void
    {
        $statusMap = [
            0 => 'waiting',
            1 => 'called',
            2 => 'in_service',
            3 => 'completed',
            4 => 'skipped',
        ];

        $status = $statusMap[$index] ?? 'waiting';

        match ($status) {
            'called' => $queueTicket->update([
                'status' => 'called',
                'called_at' => now()->subMinutes(15),
            ]),
            'in_service' => $queueTicket->update([
                'status' => 'in_service',
                'called_at' => now()->subMinutes(20),
                'serving_at' => now()->subMinutes(10),
            ]),
            'completed' => $queueTicket->update([
                'status' => 'completed',
                'called_at' => now()->subMinutes(35),
                'serving_at' => now()->subMinutes(25),
                'completed_at' => now()->subMinutes(5),
            ]),
            'skipped' => $queueTicket->update([
                'status' => 'skipped',
                'skipped_at' => now()->subMinutes(7),
            ]),
            default => null,
        };

        $queueTicket->visitRegistration()->update([
            'registration_status' => $status,
        ]);
    }

    private function seedFulfillmentAndBillingDemo(array $registrations, array $pharmacy, array $procedureMasters, array $medicalServices, array $laboratoryTests): void
    {
        $workflowService = app(ClinicalWorkflowService::class);
        $billingService = app(ClinicalBillingService::class);
        $admin = User::query()->where('email', 'clinicadmin@csi.local')->first();
        $pharmacist = User::query()->where('email', 'pharmacist.main@csi.local')->first();
        $finalVisits = collect($registrations)
            ->filter(fn (VisitRegistration $visit): bool => $visit->medicalRecord?->status === 'final')
            ->values();

        $awaitingVisit = $finalVisits->get(0);
        $completedVisit = $finalVisits->get(1);

        if ($awaitingVisit) {
            $prescription = Prescription::query()->updateOrCreate(
                ['visit_registration_id' => $awaitingVisit->id],
                [
                    'patient_id' => $awaitingVisit->patient_id,
                    'branch_id' => $awaitingVisit->branch_id,
                    'section_id' => $awaitingVisit->section_id,
                    'doctor_id' => $awaitingVisit->medicalRecord?->doctor_id,
                    'status' => 'finalized',
                    'notes' => 'Demo prescription with pending fulfillment.',
                    'finalized_at' => now()->subMinutes(20),
                    'finalized_by_user_id' => $admin?->id,
                ],
            );

            $compoundItem = $prescription->items()->updateOrCreate(
                ['display_name' => 'Compound Capsule Demo'],
                [
                    'medicine_id' => null,
                    'item_type' => 'compound',
                    'route' => 'oral',
                    'dose_amount' => 1,
                    'dose_unit' => 'capsule',
                    'frequency' => '3x sehari',
                    'duration_days' => 3,
                    'instruction' => 'Sesudah makan.',
                    'quantity_prescribed' => 9,
                    'dispense_unit' => 'capsule',
                    'weight_snapshot_kg' => 18.5,
                    'status' => 'pending',
                    'notes' => 'Racikan kapsul untuk demo fulfillment.',
                    'sort_order' => 10,
                ],
            );

            $compoundItem->compoundIngredients()->delete();
            $compoundItem->compoundIngredients()->createMany([
                ['medicine_id' => $pharmacy['PCM500']->id, 'quantity_required' => 6, 'unit' => 'tablet'],
                ['medicine_id' => $pharmacy['CTM4']->id, 'quantity_required' => 3, 'unit' => 'tablet'],
            ]);

            VisitProcedure::query()->updateOrCreate(
                [
                    'visit_registration_id' => $awaitingVisit->id,
                    'procedure_master_id' => $procedureMasters['NEB']->id,
                ],
                [
                    'branch_id' => $awaitingVisit->branch_id,
                    'section_id' => $awaitingVisit->section_id,
                    'ordered_by_doctor_id' => $awaitingVisit->medicalRecord?->doctor_id,
                    'performed_by_user_id' => null,
                    'performed_by_role' => null,
                    'quantity' => 1,
                    'unit_price' => $procedureMasters['NEB']->branchPrices->firstWhere('branch_id', $awaitingVisit->branch_id)?->price ?? $procedureMasters['NEB']->default_fee,
                    'subtotal' => $procedureMasters['NEB']->branchPrices->firstWhere('branch_id', $awaitingVisit->branch_id)?->price ?? $procedureMasters['NEB']->default_fee,
                    'status' => 'ordered',
                    'ordered_at' => now()->subMinutes(15),
                    'notes' => 'Demo pending nebulizer.',
                ],
            );

            VisitMedicalService::query()->updateOrCreate(
                [
                    'visit_registration_id' => $awaitingVisit->id,
                    'medical_service_id' => $medicalServices['OBS']->id,
                ],
                [
                    'branch_id' => $awaitingVisit->branch_id,
                    'section_id' => $awaitingVisit->section_id,
                    'ordered_by_user_id' => $admin?->id,
                    'performed_by_user_id' => null,
                    'quantity' => 1,
                    'unit_price' => $medicalServices['OBS']->branchPrices->firstWhere('branch_id', $awaitingVisit->branch_id)?->price ?? $medicalServices['OBS']->default_fee,
                    'subtotal' => $medicalServices['OBS']->branchPrices->firstWhere('branch_id', $awaitingVisit->branch_id)?->price ?? $medicalServices['OBS']->default_fee,
                    'status' => 'ordered',
                    'ordered_at' => now()->subMinutes(14),
                    'notes' => 'Demo pending observation service.',
                ],
            );

            LaboratoryOrder::query()->updateOrCreate(
                [
                    'visit_registration_id' => $awaitingVisit->id,
                    'laboratory_test_id' => $laboratoryTests['CBC']->id,
                ],
                [
                    'branch_id' => $awaitingVisit->branch_id,
                    'section_id' => $awaitingVisit->section_id,
                    'ordered_by_doctor_id' => $awaitingVisit->medicalRecord?->doctor_id,
                    'provider_type' => 'internal',
                    'partner_name' => null,
                    'external_reference_no' => null,
                    'status' => 'sample_collected',
                    'unit_price' => $laboratoryTests['CBC']->branchPrices->firstWhere('branch_id', $awaitingVisit->branch_id)?->internal_price ?? 0,
                    'ordered_at' => now()->subMinutes(18),
                    'sample_collected_at' => now()->subMinutes(12),
                    'notes' => 'CBC internal masih diproses.',
                ],
            );

            $billingService->syncVisit($awaitingVisit->fresh([
                'medicalRecord.doctor',
                'prescription.items.dispenses',
                'visitMedicalServices.medicalService',
                'visitProcedures.procedureMaster',
                'laboratoryOrders.laboratoryTest',
                'invoice.items',
            ]));
            $workflowService->refreshVisit($awaitingVisit->fresh([
                'medicalRecord',
                'prescription.items.dispenses',
                'visitMedicalServices',
                'visitProcedures',
                'laboratoryOrders',
                'invoice',
            ]));
        }

        if ($completedVisit) {
            $prescription = Prescription::query()->updateOrCreate(
                ['visit_registration_id' => $completedVisit->id],
                [
                    'patient_id' => $completedVisit->patient_id,
                    'branch_id' => $completedVisit->branch_id,
                    'section_id' => $completedVisit->section_id,
                    'doctor_id' => $completedVisit->medicalRecord?->doctor_id,
                    'status' => 'dispensed',
                    'notes' => 'Demo prescription with completed dispensing.',
                    'finalized_at' => now()->subMinutes(30),
                    'finalized_by_user_id' => $admin?->id,
                ],
            );

            $inHouseItem = $prescription->items()->updateOrCreate(
                ['display_name' => 'Paracetamol 500 mg'],
                [
                    'medicine_id' => $pharmacy['PCM500']->id,
                    'item_type' => 'in_house',
                    'route' => 'oral',
                    'dose_amount' => 1,
                    'dose_unit' => 'tablet',
                    'frequency' => '3x sehari',
                    'duration_days' => 3,
                    'instruction' => 'Sesudah makan.',
                    'quantity_prescribed' => 9,
                    'dispense_unit' => 'tablet',
                    'weight_snapshot_kg' => null,
                    'status' => 'dispensed',
                    'notes' => 'Demo in-house item.',
                    'sort_order' => 10,
                ],
            );

            $externalItem = $prescription->items()->updateOrCreate(
                ['display_name' => 'Omeprazole 20 mg'],
                [
                    'medicine_id' => $pharmacy['OMZ20']->id,
                    'item_type' => 'external',
                    'route' => 'oral',
                    'dose_amount' => 1,
                    'dose_unit' => 'capsule',
                    'frequency' => '1x sehari',
                    'duration_days' => 7,
                    'instruction' => 'Sebelum makan pagi.',
                    'quantity_prescribed' => 7,
                    'dispense_unit' => 'capsule',
                    'weight_snapshot_kg' => null,
                    'status' => 'external',
                    'notes' => 'Demo external prescription.',
                    'sort_order' => 20,
                ],
            );

            $mainBatch = MedicineBatch::query()
                ->where('branch_id', $completedVisit->branch_id)
                ->where('medicine_id', $pharmacy['PCM500']->id)
                ->orderBy('expired_at')
                ->first();

            if ($mainBatch) {
                $dispense = PrescriptionDispense::query()->updateOrCreate(
                    ['prescription_item_id' => $inHouseItem->id],
                    [
                        'visit_registration_id' => $completedVisit->id,
                        'branch_id' => $completedVisit->branch_id,
                        'dispensed_by_user_id' => $pharmacist?->id,
                        'quantity_dispensed' => 9,
                        'unit_price' => $pharmacy['PCM500']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->selling_price ?? 0,
                        'subtotal' => 9 * ($pharmacy['PCM500']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->selling_price ?? 0),
                        'dispensed_at' => now()->subMinutes(16),
                        'notes' => 'Dispensed for seed demo.',
                    ],
                );

                $dispense->batchUsages()->updateOrCreate(
                    ['medicine_batch_id' => $mainBatch->id],
                    [
                        'quantity_used' => 9,
                        'purchase_cost_snapshot' => $mainBatch->purchase_cost,
                    ],
                );

                $mainBatch->update([
                    'quantity_available' => max(0, (float) $mainBatch->quantity_available - 9),
                ]);
            }

            VisitProcedure::query()->updateOrCreate(
                [
                    'visit_registration_id' => $completedVisit->id,
                    'procedure_master_id' => $procedureMasters['DRESSING']->id,
                ],
                [
                    'branch_id' => $completedVisit->branch_id,
                    'section_id' => $completedVisit->section_id,
                    'ordered_by_doctor_id' => $completedVisit->medicalRecord?->doctor_id,
                    'performed_by_user_id' => $admin?->id,
                    'performed_by_role' => 'nurse',
                    'quantity' => 1,
                    'unit_price' => $procedureMasters['DRESSING']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->price ?? $procedureMasters['DRESSING']->default_fee,
                    'subtotal' => $procedureMasters['DRESSING']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->price ?? $procedureMasters['DRESSING']->default_fee,
                    'status' => 'completed',
                    'ordered_at' => now()->subMinutes(25),
                    'started_at' => now()->subMinutes(24),
                    'completed_at' => now()->subMinutes(21),
                    'notes' => 'Demo completed dressing.',
                ],
            );

            VisitMedicalService::query()->updateOrCreate(
                [
                    'visit_registration_id' => $completedVisit->id,
                    'medical_service_id' => $medicalServices['NURSE-FEE']->id,
                ],
                [
                    'branch_id' => $completedVisit->branch_id,
                    'section_id' => $completedVisit->section_id,
                    'ordered_by_user_id' => $admin?->id,
                    'performed_by_user_id' => $admin?->id,
                    'quantity' => 1,
                    'unit_price' => $medicalServices['NURSE-FEE']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->price ?? $medicalServices['NURSE-FEE']->default_fee,
                    'subtotal' => $medicalServices['NURSE-FEE']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->price ?? $medicalServices['NURSE-FEE']->default_fee,
                    'status' => 'completed',
                    'ordered_at' => now()->subMinutes(28),
                    'completed_at' => now()->subMinutes(20),
                    'notes' => 'Demo completed nurse service.',
                ],
            );

            $externalLab = LaboratoryOrder::query()->updateOrCreate(
                [
                    'visit_registration_id' => $completedVisit->id,
                    'laboratory_test_id' => $laboratoryTests['GLU']->id,
                ],
                [
                    'branch_id' => $completedVisit->branch_id,
                    'section_id' => $completedVisit->section_id,
                    'ordered_by_doctor_id' => $completedVisit->medicalRecord?->doctor_id,
                    'provider_type' => 'external',
                    'partner_name' => 'Partner Lab Nusantara',
                    'external_reference_no' => 'EXT-LAB-001',
                    'status' => 'sent_to_partner',
                    'unit_price' => $laboratoryTests['GLU']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->external_price ?? 0,
                    'ordered_at' => now()->subMinutes(22),
                    'sent_to_partner_at' => now()->subMinutes(18),
                    'result_attachment_path' => 'partner-results/demo-glucose.pdf',
                    'notes' => 'External glucose check.',
                ],
            );

            LaboratoryOrder::query()->updateOrCreate(
                [
                    'visit_registration_id' => $completedVisit->id,
                    'laboratory_test_id' => $laboratoryTests['CXR']->id,
                ],
                [
                    'branch_id' => $completedVisit->branch_id,
                    'section_id' => $completedVisit->section_id,
                    'ordered_by_doctor_id' => $completedVisit->medicalRecord?->doctor_id,
                    'provider_type' => 'external',
                    'partner_name' => 'Radiology Partner Medan',
                    'external_reference_no' => 'RAD-CXR-001',
                    'status' => 'reviewed',
                    'unit_price' => $laboratoryTests['CXR']->branchPrices->firstWhere('branch_id', $completedVisit->branch_id)?->external_price ?? 0,
                    'ordered_at' => now()->subMinutes(24),
                    'sent_to_partner_at' => now()->subMinutes(22),
                    'resulted_at' => now()->subMinutes(12),
                    'reviewed_at' => now()->subMinutes(9),
                    'reviewed_by_user_id' => $admin?->id,
                    'result_summary' => 'No focal infiltrate. Cardiomediastinal silhouette within normal limits.',
                    'result_impression' => 'No active cardiopulmonary disease.',
                    'result_attachment_path' => 'partner-results/demo-cxr.pdf',
                    'notes' => 'Demo reviewed radiology result.',
                ],
            );

            LaboratoryResultEntry::query()->updateOrCreate(
                [
                    'laboratory_order_id' => $externalLab->id,
                    'parameter_name' => 'Glucose',
                ],
                [
                    'parameter_code' => 'GLU',
                    'value' => '132',
                    'unit' => 'mg/dL',
                    'reference_range' => '70 - 140',
                    'result_flag' => 'normal',
                    'notes' => 'Seed external result summary.',
                    'sort_order' => 10,
                ],
            );

            $invoice = $billingService->syncVisit($completedVisit->fresh([
                'medicalRecord.doctor',
                'prescription.items.dispenses',
                'visitMedicalServices.medicalService',
                'visitProcedures.procedureMaster',
                'laboratoryOrders.laboratoryTest',
                'invoice.items',
            ]));

            Invoice::query()->whereKey($invoice->id)->update([
                'status' => 'paid',
                'paid_amount' => $invoice->total_amount,
                'paid_at' => now()->subMinutes(10),
                'paid_by_user_id' => $admin?->id,
                'notes' => 'Seed paid invoice.',
            ]);

            $workflowService->refreshVisit($completedVisit->fresh([
                'medicalRecord',
                'prescription.items.dispenses',
                'visitMedicalServices',
                'visitProcedures',
                'laboratoryOrders',
                'invoice',
            ]));
        }
    }
}
