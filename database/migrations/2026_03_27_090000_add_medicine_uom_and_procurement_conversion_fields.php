<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medicine_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('medicine_id')->constrained()->cascadeOnDelete();
            $table->string('label', 40);
            $table->decimal('conversion_factor', 12, 4)->default(1);
            $table->boolean('is_base')->default(false);
            $table->boolean('allow_purchase')->default(false);
            $table->boolean('allow_dispense')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['medicine_id', 'label']);
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->foreignId('medicine_unit_id')->nullable()->after('medicine_id')->constrained('medicine_units')->nullOnDelete();
            $table->string('unit_label', 40)->nullable()->after('medicine_unit_id');
            $table->decimal('conversion_factor', 12, 4)->default(1)->after('unit_label');
            $table->decimal('quantity_ordered_base', 12, 2)->default(0)->after('quantity_ordered');
            $table->decimal('unit_cost_base', 12, 4)->default(0)->after('unit_cost');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->foreignId('medicine_unit_id')->nullable()->after('medicine_id')->constrained('medicine_units')->nullOnDelete();
            $table->string('unit_label', 40)->nullable()->after('medicine_unit_id');
            $table->decimal('conversion_factor', 12, 4)->default(1)->after('unit_label');
            $table->decimal('quantity_received_base', 12, 2)->default(0)->after('quantity_received');
            $table->decimal('unit_cost_base', 12, 4)->default(0)->after('unit_cost');
        });

        $medicineBaseUnits = [];

        DB::table('medicines')
            ->orderBy('id')
            ->get(['id', 'base_unit'])
            ->each(function (object $medicine) use (&$medicineBaseUnits): void {
                $label = strtoupper(trim((string) ($medicine->base_unit ?: 'UNIT')));
                $unitId = DB::table('medicine_units')->insertGetId([
                    'medicine_id' => $medicine->id,
                    'label' => $label,
                    'conversion_factor' => 1,
                    'is_base' => true,
                    'allow_purchase' => true,
                    'allow_dispense' => true,
                    'sort_order' => 10,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $medicineBaseUnits[(int) $medicine->id] = [
                    'id' => $unitId,
                    'label' => $label,
                ];

                DB::table('medicines')
                    ->where('id', $medicine->id)
                    ->update([
                        'base_unit' => $label,
                    ]);
            });

        DB::table('purchase_order_items')
            ->orderBy('id')
            ->get(['id', 'medicine_id', 'quantity_ordered', 'unit_cost'])
            ->each(function (object $item) use ($medicineBaseUnits): void {
                $unit = $medicineBaseUnits[(int) $item->medicine_id] ?? null;

                if ($unit === null) {
                    return;
                }

                DB::table('purchase_order_items')
                    ->where('id', $item->id)
                    ->update([
                        'medicine_unit_id' => $unit['id'],
                        'unit_label' => $unit['label'],
                        'conversion_factor' => 1,
                        'quantity_ordered_base' => $item->quantity_ordered,
                        'unit_cost_base' => $item->unit_cost,
                    ]);
            });

        DB::table('goods_receipt_items')
            ->orderBy('id')
            ->get(['id', 'medicine_id', 'quantity_received', 'unit_cost'])
            ->each(function (object $item) use ($medicineBaseUnits): void {
                $unit = $medicineBaseUnits[(int) $item->medicine_id] ?? null;

                if ($unit === null) {
                    return;
                }

                DB::table('goods_receipt_items')
                    ->where('id', $item->id)
                    ->update([
                        'medicine_unit_id' => $unit['id'],
                        'unit_label' => $unit['label'],
                        'conversion_factor' => 1,
                        'quantity_received_base' => $item->quantity_received,
                        'unit_cost_base' => $item->unit_cost,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('medicine_unit_id');
            $table->dropColumn([
                'unit_label',
                'conversion_factor',
                'quantity_received_base',
                'unit_cost_base',
            ]);
        });

        Schema::table('purchase_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('medicine_unit_id');
            $table->dropColumn([
                'unit_label',
                'conversion_factor',
                'quantity_ordered_base',
                'unit_cost_base',
            ]);
        });

        Schema::dropIfExists('medicine_units');
    }
};
