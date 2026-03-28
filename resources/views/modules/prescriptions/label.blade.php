<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispense Label</title>
    <style>
        @page {
            size: 80mm auto;
            margin: 6mm;
        }

        body {
            font-family: Arial, sans-serif;
            color: #111827;
            margin: 0;
            font-size: 12px;
            line-height: 1.45;
        }

        .label {
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
        }

        .heading {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .muted {
            color: #6b7280;
            font-size: 11px;
        }

        .section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #d1d5db;
        }

        .item-name {
            font-size: 16px;
            font-weight: 700;
            margin: 8px 0 2px;
        }

        .instruction {
            margin-top: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin-top: 4px;
        }

        .print-actions {
            margin: 16px auto 0;
            width: min(80mm, 100%);
            display: flex;
            gap: 8px;
        }

        .print-actions button {
            flex: 1;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: white;
            cursor: pointer;
        }

        @media print {
            .print-actions {
                display: none;
            }
        }
    </style>
</head>
<body>
    @php
        $patient = $visit?->patient;
        $patientRecord = $visit?->patientBranchRecord;
        $branch = $visit?->branch;
        $doctor = $visit?->medicalRecord?->doctor;
    @endphp

    <div class="label">
        <div class="heading">{{ $branch?->name ?? 'CSI Clinic' }}</div>
        <div class="muted">{{ $branch?->code ?? '-' }} | Dispense label</div>

        <div class="section">
            <div><strong>Pasien:</strong> {{ $patient?->full_name ?? '-' }}</div>
            <div class="muted">RM {{ $patientRecord?->medical_record_no ?? '-' }} | {{ $patient?->phone ?? '-' }}</div>
        </div>

        <div class="section">
            <div class="item-name">{{ $item?->display_name ?? '-' }}</div>
            <div class="muted">
                {{ $item?->route ?: '-' }} | Qty {{ number_format((float) ($dispense?->quantity_dispensed ?? 0), 2) }} {{ $item?->dispense_unit ?: ($item?->medicine?->base_unit ?? '') }}
            </div>
            <div class="instruction">{{ $item?->instruction ?: 'Gunakan sesuai instruksi dokter.' }}</div>
            <div class="row">
                <span>Dosis</span>
                <span>{{ $item?->dose_amount ?: '-' }} {{ $item?->dose_unit ?: '' }}</span>
            </div>
            <div class="row">
                <span>Frekuensi</span>
                <span>{{ $item?->frequency ?: '-' }}</span>
            </div>
            <div class="row">
                <span>Durasi</span>
                <span>{{ $item?->duration_days ?: '-' }} hari</span>
            </div>
        </div>

        @if ($item?->isCompound())
            <div class="section">
                <div><strong>Komposisi racikan</strong></div>
                @foreach ($item->compoundIngredients as $ingredient)
                    <div class="row">
                        <span>{{ $ingredient->medicine?->name ?? '-' }}</span>
                        <span>{{ number_format((float) $ingredient->quantity_required, 2) }} {{ $ingredient->unit ?: ($ingredient->medicine?->base_unit ?? '') }}</span>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="section">
            <div class="row">
                <span>Dokter</span>
                <span>{{ $doctor?->displayName() ?? '-' }}</span>
            </div>
            <div class="row">
                <span>Dispensed</span>
                <span>{{ $dispense?->dispensed_at?->format('d M Y H:i') ?? '-' }}</span>
            </div>
            <div class="row">
                <span>Petugas</span>
                <span>{{ $dispense?->dispensedBy?->name ?? '-' }}</span>
            </div>
        </div>
    </div>

    <div class="print-actions">
        <button type="button" onclick="window.print()">Print label</button>
        <button type="button" onclick="window.close()">Close</button>
    </div>
</body>
</html>
