<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 150);
            $table->string('gender', 20);
            $table->date('date_of_birth');
            $table->string('nik', 30)->nullable()->unique();
            $table->string('phone', 30);
            $table->string('email')->nullable();
            $table->string('province_code', 20)->nullable();
            $table->string('province_name', 120)->nullable();
            $table->string('city_code', 20)->nullable();
            $table->string('city_name', 120)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->string('district_name', 120)->nullable();
            $table->string('village_code', 20)->nullable();
            $table->string('village_name', 120)->nullable();
            $table->text('address_line')->nullable();
            $table->text('allergy_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['full_name', 'date_of_birth']);
            $table->index('phone');
            $table->index('province_code');
            $table->index('city_code');
            $table->index('district_code');
            $table->index('village_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
