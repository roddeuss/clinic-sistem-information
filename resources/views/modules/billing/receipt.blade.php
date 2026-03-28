<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $invoice->invoice_no }} - Payment Receipt</title>
    <style>
        @page { size: 80mm auto; margin: 5mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #eef2f7; color: #111827; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 18px 22px; background: #111827; color: #fff; }
        .toolbar-actions { display: flex; gap: 10px; }
        .toolbar button, .toolbar a { border: 0; border-radius: 10px; padding: 10px 14px; font-size: 13px; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #10b981; color: #fff; }
        .toolbar a { background: #fff; color: #111827; }
        .receipt { width: 80mm; margin: 18px auto; background: #fff; box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14); padding: 14px 12px 18px; }
        .center { text-align: center; }
        .clinic { font-size: 16px; font-weight: 700; }
        .subtle { font-size: 11px; color: #6b7280; line-height: 1.5; }
        .divider { border-top: 1px dashed #9ca3af; margin: 10px 0; }
        .meta-row, .total-row, .item-row { display: flex; justify-content: space-between; gap: 10px; font-size: 12px; }
        .meta-row { margin-bottom: 4px; }
        .item-row { align-items: flex-start; padding: 5px 0; }
        .item-row .desc { flex: 1; }
        .item-row .amount, .total-row strong, .total-row span:last-child { text-align: right; white-space: nowrap; }
        .totals { margin-top: 10px; }
        .total-row { padding: 3px 0; }
        .total-row.grand { border-top: 1px solid #111827; margin-top: 6px; padding-top: 8px; font-size: 14px; font-weight: 700; }
        .badge { display: inline-flex; border-radius: 999px; padding: 5px 10px; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.unpaid { background: #fef3c7; color: #92400e; }
        .badge.voided { background: #fee2e2; color: #991b1b; }
        .footer-note { margin-top: 12px; font-size: 11px; text-align: center; color: #6b7280; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { margin: 0 auto; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div style="font-size: 16px; font-weight: 700;">Receipt Thermal</div>
            <div style="font-size: 12px; color: rgba(255,255,255,0.72);">Cetak 80mm atau simpan sebagai PDF.</div>
        </div>
        <div class="toolbar-actions">
            <a href="{{ route('billing') }}">Kembali</a>
            <button type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <main class="receipt">
        <section class="center">
            <div class="clinic">{{ $invoice->branch?->clinic?->name ?? 'CSI Clinic' }}</div>
            <div class="subtle">
                {{ $invoice->branch?->name ?? '-' }}<br>
                {{ $invoice->branch?->address ?? '-' }}<br>
                {{ $invoice->branch?->phone ?? $invoice->branch?->clinic?->phone ?? '-' }}
            </div>
            <div style="margin-top: 10px;">
                <span class="badge {{ $invoice->status }}">{{ $invoice->status }}</span>
            </div>
        </section>

        <div class="divider"></div>

        <section>
            <div class="meta-row"><span>Invoice</span><strong>{{ $invoice->invoice_no }}</strong></div>
            <div class="meta-row"><span>Tanggal</span><span>{{ $invoice->paid_at?->format('d/m/Y H:i') ?? $invoice->issued_at?->format('d/m/Y H:i') ?? $invoice->created_at?->format('d/m/Y H:i') }}</span></div>
            <div class="meta-row"><span>Pasien</span><span>{{ $invoice->patient?->full_name ?? '-' }}</span></div>
            <div class="meta-row"><span>No RM</span><span>{{ $invoice->patientBranchRecord?->medical_record_no ?? '-' }}</span></div>
            <div class="meta-row"><span>Poli</span><span>{{ $invoice->visitRegistration?->section?->name ?? '-' }}</span></div>
            <div class="meta-row"><span>Payment</span><span>{{ $invoice->paymentMethod?->name ?? '-' }}</span></div>
            <div class="meta-row"><span>Kasir</span><span>{{ $invoice->paidBy?->name ?? '-' }}</span></div>
            <div class="meta-row"><span>Shift</span><span>{{ $invoice->cashierShift?->shift_code ?? '-' }}</span></div>
        </section>

        <div class="divider"></div>

        <section>
            @forelse ($invoice->items as $item)
                <div class="item-row">
                    <div class="desc">
                        <div style="font-weight: 700;">{{ $item->description }}</div>
                        <div class="subtle">{{ strtoupper($item->item_type) }} | {{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ','), '0'), '.') }} x Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</div>
                    </div>
                    <div class="amount">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</div>
                </div>
            @empty
                <div class="subtle center">Belum ada item invoice.</div>
            @endforelse
        </section>

        <div class="divider"></div>

        <section class="totals">
            <div class="total-row"><span>Subtotal</span><span>Rp {{ number_format((float) $invoice->subtotal, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Diskon</span><span>Rp {{ number_format((float) $invoice->discount_amount, 0, ',', '.') }}</span></div>
            <div class="total-row grand"><span>Total</span><span>Rp {{ number_format((float) $invoice->total_amount, 0, ',', '.') }}</span></div>
        </section>

        <div class="divider"></div>

        <section class="subtle center">
            @if ($invoice->payment_reference)
                Ref: {{ $invoice->payment_reference }}<br>
            @endif
            Printed: {{ $invoice->printed_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
        </section>

        <div class="footer-note">
            Terima kasih. Bukti pembayaran ini dihasilkan dari CSI Clinic System Dashboard.
        </div>
    </main>
</body>
</html>
