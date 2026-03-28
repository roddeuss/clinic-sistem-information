<?php

namespace Tests\Feature\Icd10;

use App\Models\Icd10Code;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Icd10ManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_clinic_admin_can_open_and_manage_icd10_master(): void
    {
        $user = User::query()->where('email', 'clinicadmin@csi.local')->firstOrFail();
        $unusedCode = Icd10Code::query()->where('code', 'H10.9')->firstOrFail();

        $this->actingAs($user)
            ->get(route('icd10'))
            ->assertOk()
            ->assertSee('ICD-10 Master');

        $this->actingAs($user)
            ->post(route('icd10.store'), [
                'chapter_code' => 'XVIII',
                'code' => 'R42',
                'name_en' => 'Dizziness and giddiness',
                'name_id' => 'Pusing dan vertigo',
                'description' => 'Demo manual insert for ICD-10.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('icd10'));

        $this->assertDatabaseHas('icd10_codes', [
            'code' => 'R42',
            'name_id' => 'Pusing dan vertigo',
        ]);

        $this->actingAs($user)
            ->post(route('icd10.update', $unusedCode), [
                'chapter_code' => 'VII',
                'code' => 'H10.9',
                'name_en' => 'Conjunctivitis, unspecified',
                'name_id' => 'Konjungtivitis revisi',
                'description' => 'Updated by feature test.',
                'is_active' => 1,
            ])
            ->assertRedirect(route('icd10'));

        $this->assertDatabaseHas('icd10_codes', [
            'id' => $unusedCode->id,
            'name_id' => 'Konjungtivitis revisi',
        ]);

        $createdCode = Icd10Code::query()->where('code', 'R42')->firstOrFail();

        $this->actingAs($user)
            ->post(route('icd10.delete', $createdCode))
            ->assertRedirect(route('icd10'));

        $this->assertDatabaseHas('icd10_codes', [
            'id' => $createdCode->id,
            'is_active' => false,
        ]);
    }
}
