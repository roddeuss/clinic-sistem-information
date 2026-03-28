@php
    $prescriptionOptions = $visitOptions->filter(fn ($visitOption) => $visitOption->prescription)->values();
    $defaultVisitId = (string) ($visitOptions->first()?->id ?? '');
    $defaultPrescriptionId = (string) ($prescriptionOptions->first()?->prescription?->id ?? '');

    $defaultIngredientRows = collect(old('compound_ingredients', [
        ['medicine_id' => '', 'quantity_required' => '', 'unit' => ''],
        ['medicine_id' => '', 'quantity_required' => '', 'unit' => ''],
        ['medicine_id' => '', 'quantity_required' => '', 'unit' => ''],
    ]))->map(fn ($row) => [
        'medicine_id' => (string) ($row['medicine_id'] ?? ''),
        'quantity_required' => (string) ($row['quantity_required'] ?? ''),
        'unit' => $row['unit'] ?? '',
    ])->pad(3, ['medicine_id' => '', 'quantity_required' => '', 'unit' => ''])->take(3)->values()->all();

    $prescriptionModalOpen = $errors->any() && in_array(old('form_context'), ['prescription-create', 'prescription-update'], true);
    $prescriptionModalMode = old('form_context') === 'prescription-update' ? 'update' : 'create';
    $itemModalOpen = $errors->any() && in_array(old('form_context'), ['prescription-item-create', 'prescription-item-update'], true);
    $itemModalMode = old('form_context') === 'prescription-item-update' ? 'update' : 'create';
    $dispenseModalOpen = $errors->any() && old('form_context') === 'prescription-dispense';
    $closeModalOpen = $errors->any() && old('form_context') === 'prescription-close-remaining';
    $overrideModalOpen = $errors->any() && old('form_context') === 'prescription-override';

    $prescriptionModalForm = [
        'id' => old('entity_id'),
        'visit_registration_id' => (string) old('visit_registration_id', $defaultVisitId),
        'notes' => old('notes', ''),
    ];
    $itemModalForm = [
        'id' => old('entity_id'),
        'prescription_id' => (string) old('prescription_id', $defaultPrescriptionId),
        'item_type' => old('item_type', 'in_house'),
        'medicine_id' => (string) old('medicine_id', ''),
        'display_name' => old('display_name', ''),
        'route' => old('route', ''),
        'dose_amount' => old('dose_amount', ''),
        'dose_unit' => old('dose_unit', ''),
        'frequency' => old('frequency', ''),
        'duration_days' => old('duration_days', ''),
        'instruction' => old('instruction', ''),
        'quantity_prescribed' => old('quantity_prescribed', 1),
        'dispense_unit' => old('dispense_unit', ''),
        'weight_snapshot_kg' => old('weight_snapshot_kg', ''),
        'status' => old('status', 'pending'),
        'notes' => old('notes', ''),
        'compound_ingredients' => $defaultIngredientRows,
    ];
    $dispenseModalForm = [
        'id' => old('entity_id'),
        'quantity_dispensed' => old('quantity_dispensed', ''),
        'unit_price' => old('unit_price', ''),
        'notes' => old('notes', ''),
    ];
    $closeModalForm = [
        'id' => old('entity_id'),
        'closure_status' => old('closure_status', 'external'),
        'closure_reason' => old('closure_reason', ''),
    ];
    $overrideModalForm = [
        'id' => old('entity_id'),
        'override_reason' => old('override_reason', ''),
    ];
@endphp

@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Prescription" />

    <div
        x-data="prescriptionDesk({
            prescriptionModalOpen: @js($prescriptionModalOpen),
            prescriptionMode: @js($prescriptionModalMode),
            itemModalOpen: @js($itemModalOpen),
            itemMode: @js($itemModalMode),
            dispenseModalOpen: @js($dispenseModalOpen),
            closeModalOpen: @js($closeModalOpen),
            overrideModalOpen: @js($overrideModalOpen),
            prescriptionStoreAction: @js(route('prescriptions.store')),
            prescriptionUpdateBase: @js(url('/prescriptions')),
            itemStoreAction: @js(route('prescription-items.store')),
            itemUpdateBase: @js(url('/prescription-items')),
            itemActionBase: @js(url('/prescription-items')),
            defaultVisitId: @js($defaultVisitId),
            defaultPrescriptionId: @js($defaultPrescriptionId),
            prescriptionForm: @js($prescriptionModalForm),
            itemForm: @js($itemModalForm),
            dispenseForm: @js($dispenseModalForm),
            closeForm: @js($closeModalForm),
            overrideForm: @js($overrideModalForm),
        })"
        @keydown.escape.window="closeAll()"
        class="space-y-6"
    >
        @if (session('status'))
            <x-ui.alert variant="success" :message="session('status')" />
        @endif

        @if ($errors->any())
            <x-ui.alert variant="error" :message="$errors->first()" />
        @endif

        @include('modules.prescriptions.partials.header')
        @include('modules.prescriptions.partials.metrics')
        @include('modules.prescriptions.partials.filters')
        @include('modules.prescriptions.partials.visit-table')
        @include('modules.prescriptions.partials.dispense-table')
        @include('modules.prescriptions.partials.modals')
    </div>
@endsection

@include('modules.prescriptions.partials.script')
