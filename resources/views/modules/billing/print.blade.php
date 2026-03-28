<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_no }} - Sales Invoice</title>
    <style>
        @page { size: A4; margin: 18mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; color: #111827; margin: 0; background: #eef2f7; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 20px 24px; background: #111827; color: #fff; }
        .toolbar-actions { display: flex; gap: 10px; }
        .toolbar button, .toolbar a { border: 0; border-radius: 10px; padding: 10px 14px; font-size: 14px; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #4f46e5; color: #fff; }
        .toolbar a { background: #fff; color: #111827; }
        .sheet { width: 210mm; min-height: 297mm; margin: 24px auto; background: #fff; box-shadow: 0 14px 35px rgba(15, 23, 42, 0.15); padding: 22mm 18mm; }
        .header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 18px; border-bottom: 2px solid #e5e7eb; }
        .title { font-size: 28px; font-weight: 700; margin: 0 0 8px; }
        .meta { display: grid; gap: 6px; font-size: 13px; color: #4b5563; }
        .invoice-card { min-width: 240px; border: 1px solid #d1d5db; border-radius: 14px; padding: 16px; }
        .badge { display: inline-flex; border-radius: 999px; padding: 6px 10px; font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.unpaid { background: #fef3c7; color: #92400e; }
        .badge.voided { background: #fee2e2; color: #991b1b; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 20px; }
        .panel { border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; }
        .panel h3 { margin: 0 0 10px; font-size: 13px; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; }
        .panel p { margin: 0 0 6px; font-size: 14px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        thead th { text-align: left; font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: #6b7280; background: #f9fafb; padding: 12px; border-bottom: 1px solid #e5e7eb; }
        tbody td { padding: 12px; border-bottom: 1px solid #e5e7eb; font-size: 14px; vertical-align: top; }
        .totals { margin-left: auto; width: 320px; margin-top: 20px; }
        .totals-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; font-size: 14px; color: #4b5563; }
        .totals-row.total { border-top: 2px solid #111827; margin-top: 6px; padding-top: 12px; font-size: 18px; font-weight: 700; color: #111827; }
        .notes { margin-top: 20px; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; font-size: 14px; color: #374151; }
        .footer { margin-top: 28px; padding-top: 16px; border-top: 1px dashed #d1d5db; font-size: 12px; color: #6b7280; display: flex; justify-content: space-between; gap: 20px; }
        .mono { font-family: "Courier New", monospace; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div style="font-size: 18px; font-weight: 700;">Sales Invoice A4</div>
            <div style="font-size: 13px; color: rgba(255,255,255,0.72);">Gunakan print browser untuk menyimpan PDF invoice.</div>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('billing') }}">Kembali</a>
            <button type="button" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <main class="sheet">
        <section class="header">
            <div>
                <h1 class="title">{{ $invoice->branch?->clinic?->name ?? 'CSI Clinic' }}</h1>
                <div class="meta">
                    @if ($invoice->branch?->clinic?->invoice_header)
                        @foreach (preg_split("/\r\n|\n|\r/", $invoice->branch->clinic->invoice_header) as $line)
                            @if (filled($line))
                                <div>{{ $line }}</div>
                            @endif
                        @endforeach
                    @endif
                    <div>{{ $invoice->branch?->address ?? '-' }}</div>
                    <div>{{ $invoice->branch?->phone ?? $invoice->branch?->clinic?->phone ?? '-' }}</div>
                </div>
            </div>
            <div class="invoice-card">
                <span class="badge {{ $invoice->status }}">{{ $invoice->status }}</span>
                <div style="margin-top: 12px; font-size: 22px; font-weight: 700;">{{ $invoice->invoice_no }}</div>
                <div class="meta" style="margin-top: 12px;">
                    <div>Issue Date: {{ $invoice->issued_at?->format('d M Y H:i') ?? $invoice->created_at?->format('d M Y H:i') }}</div>
                    <div>Branch: {{ $invoice->branch?->code }} - {{ $invoice->branch?->name }}</div>
                    <div>Section: {{ $invoice->visitRegistration?->section?->name ?? '-' }}</div>
                    <div>Printed At: {{ $invoice->printed_at?->format('d M Y H:i') ?? now()->format('d M Y H:i') }}</div>
                </div>
            </div>
        </section>

        <section class="grid">
            <div class="panel">
                <h3>Bill To</h3>
                <p><strong>{{ $invoice->patient?->full_name ?? '-' }}</strong></p>
                <p>MR No: <span class="mono">{{ $invoice->patientBranchRecord?->medical_record_no ?? '-' }}</span></p>
                <p>Phone: {{ $invoice->patient?->phone ?? '-' }}</p>
                <p>Visit Date: {{ $invoice->visitRegistration?->visit_date?->format('d M Y') ?? '-' }}</p>
            </div>
            <div class="panel">
                <h3>Payment</h3>
                <p>Method: {{ $invoice->paymentMethod?->name ?? '-' }}</p>
                <p>Shift: {{ $invoice->cashierShift?->shift_code ?? '-' }}</p>
                <p>Cashier: {{ $invoice->paidBy?->name ?? '-' }}</p>
                <p>Paid At: {{ $invoice->paid_at?->format('d M Y H:i') ?? '-' }}</p>
                <p>Reference: {{ $invoice->payment_reference ?? '-' }}</p>
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th style="width: 18%;">Type</th>
                    <th>Description</th>
                    <th style="width: 12%;">Qty</th>
                    <th style="width: 18%;">Unit Price</th>
                    <th style="width: 18%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td>{{ strtoupper($item->item_type) }}</td>
                        <td>{{ $item->description }}</td>
                        <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ','), '0'), '.') }}</td>
                        <td>Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                        <td>Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">Belum ada item invoice.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <section class="totals">
            <div class="totals-row"><span>Subtotal</span><strong>Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</strong></div>
            <div class="totals-row"><span>Discount</span><strong>Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}</strong></div>
            <div class="totals-row total"><span>Total</span><span>Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</span></div>
        </section>

        <section class="notes">
            <strong>Notes</strong>
            <div style="margin-top: 8px;">{{ $invoice->void_reason ?: ($invoice->notes ?: 'Tidak ada catatan tambahan.') }}</div>
        </section>

        <section class="footer">
            <div>Dokumen ini dihasilkan dari CSI Clinic System Dashboard.</div>
            <div>Invoice status: <strong>{{ strtoupper($invoice->status) }}</strong></div>
        </section>
    </main>
</body>
</html>
