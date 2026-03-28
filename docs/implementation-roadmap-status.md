# CSI Clinic HIS Implementation Roadmap

Status per 28 Maret 2026

## Legend

- `READY`
  Modul inti sudah ada, bisa dipakai, dan alur dasarnya sudah jalan.
- `PARTIAL`
  Fondasi atau sebagian flow sudah ada, tapi masih ada gap penting sebelum dianggap selesai.
- `NOT STARTED`
  Belum ada modul khusus atau requirement intinya belum terpenuhi.

## Summary

- `READY`: 43 fitur
- `PARTIAL`: 1 fitur
- `NOT STARTED`: 11 fitur

## MVP

| No | Feature | Status | Notes |
| --- | --- | --- | --- |
| 01 | User management | READY | Login, profile, password reset, session management sudah ada. |
| 02 | Role & permission | READY | RBAC per modul sudah ada. |
| 03 | Menu & menu category | READY | Sidebar sudah dibaca dari database. |
| 04 | Clinic / branch settings | READY | Clinic profile dan branch settings sudah ada. |
| 05 | Poli / section | READY | Section regular dan emergency sudah ada per branch. |
| 06 | Counter / kasir | READY | Counter per branch sudah ada. |
| 07 | Doctor management | READY | Master dokter, STR/SIP, spesialisasi, tarif konsultasi sudah ada. |
| 08 | Doctor schedule | READY | Jadwal dokter, slot, dan leave sudah ada. |
| 09 | Patient management | READY | Master pasien, RM per branch, alergi dasar, dan region lookup sudah ada. |
| 10 | Queue / antrian realtime | READY | Queue desk realtime, display board, Reverb broadcast, dan polling fallback sudah ada. |
| 11 | Vital signs input | READY | Input vital signs sebelum pemeriksaan dokter sudah ada. |
| 12 | SOAP / medical record | READY | SOAP, final, request reopen, approve reopen sudah ada. |
| 13 | ICD-10 master data | READY | Master ICD-10 dan pemilihan diagnosis sudah ada. |
| 14 | Resep obat | READY | Prescription, item, finalize, in-house, external, dan racikan kapsul sudah ada. |
| 15 | Category produk | READY | Product category flat sudah ada. |
| 16 | Products / medicine / drugs | READY | Medicine master, multi-level UOM, base-unit conversion, batch, stock, dan expired monitoring sudah ada. |
| 17 | Services medical | READY | Service catalog umum, branch pricing, service order, dan sinkron billing sudah ada. |
| 18 | Supplier management | READY | Master supplier, NPWP, termin pembayaran, dan arsip supplier sudah ada. |
| 19 | Payment method | READY | Cash, transfer, debit/credit, QRIS sudah bisa dimasterkan. |
| 20 | Sales invoice | READY | Invoice, payment, status paid/void, print A4, dan receipt thermal sudah ada. |
| 21 | Shift kasir / cashier session | READY | Open shift, active shift, close shift sudah ada. |
| 22 | Audit log | READY | Audit log sudah ada dan bisa dilihat role tertentu. |

## Phase 1

| No | Feature | Status | Notes |
| --- | --- | --- | --- |
| 23 | Purchase order | READY | PO draft, submit, update, cancel, archive sudah ada. |
| 24 | PO approval workflow | READY | Approval threshold, submit, approve, reject, dan audit flow sudah ada. |
| 25 | Good receipts | READY | Goods receipt dari PO, batch, expired, qty received sudah ada. |
| 26 | Dispensing / pharmacy workflow | READY | Partial dispense, close remaining, FEFO allocation, stock deduction, dan sync billing sudah ada. |
| 27 | Etiket obat / medicine label print | READY | Label print untuk hasil dispense sudah ada. |
| 28 | Riwayat alergi & kontraindikasi | READY | Alert alergi ingredient, duplicate therapy, dan contraindication notes sudah ada saat prescription/dispensing. |
| 29 | Purchase return | READY | Return ke supplier dan pengurangan stok sudah ada. |
| 30 | Adjustment stok | READY | Stock adjustment increase/decrease sudah ada. |
| 31 | Expired medicine monitor | READY | Monitoring expired dan quarantine/release sudah ada. |
| 32 | Stock opname | READY | Draft, finalize, selisih stok, dan koreksi stok sudah ada. |
| 33 | In-app notification center | READY | Bell notification, unread count, mark as read, dan halaman notification center sudah ada. |

## Phase 2

| No | Feature | Status | Notes |
| --- | --- | --- | --- |
| 34 | Tindakan medis / medical procedures | READY | Procedure master, order, performer scope, branch pricing sudah ada. |
| 35 | Piutang pasien / receivable | READY | Self-pay tempo, receivable perusahaan, cicilan, extend due date, settle, dan cancel flow sudah ada. |
| 36 | Rujukan pasien / referral | READY | Referral external, master tujuan rujukan, issue, print, void, dan reissue per branch sudah ada. |
| 37 | Surat keterangan dokter | READY | Surat sakit, surat sehat, surat kontrol, surat bebas narkoba, void, reissue, dan print PDF sudah ada. |
| 38 | Lab / penunjang order | READY | Diagnostics internal/external untuk laboratory, radiology, dan support order sudah ada dengan request print, reviewed result print, hasil structured/narrative, dan metadata reviewer. |
| 39 | Report lengkap | READY | Report operasional detail, dataset drilldown, dan export Excel/PDF sudah ada untuk visits, invoices, receivables, inventory, procurement, dan diagnostics. |
| 40 | Backup & restore data | READY | Manual database backup ZIP, download, restore full database, dan audit trail super-admin sudah ada. |

## Phase 3

| No | Feature | Status | Notes |
| --- | --- | --- | --- |
| 41 | Multi-tenant / multi-branch | PARTIAL | Multi-branch ada, tenant isolation SaaS belum ada. |
| 42 | Subscription & billing SaaS | NOT STARTED | Belum ada. |
| 43 | AI symptom checker | NOT STARTED | Belum ada. |
| 44 | AI allergy & drug detector | NOT STARTED | Belum ada. |
| 45 | Patient portal | NOT STARTED | Belum ada. |
| 46 | Analytics dashboard | NOT STARTED | Belum ada. |

## Bonus

| No | Feature | Status | Notes |
| --- | --- | --- | --- |
| 47 | Antrian digital self-check-in | NOT STARTED | Belum ada. |
| 48 | Display antrian TV mode | READY | Halaman display queue realtime untuk layar tunggu sudah ada. |
| 49 | Drug interaction checker | READY | Rule management, checker pada prescription dan dispensing, active medication comparison, major override dengan alasan, dan hard stop contraindicated/allergy severe sudah ada. |
| 50 | Reorder point otomatis | READY | Policy per branch dan medicine, multi-supplier priority, purchase UOM recommendation, low stock notification, dan reorder recommendation sudah ada. |
| 51 | Patient satisfaction survey | NOT STARTED | Belum ada. |
| 52 | Medicine price comparison | NOT STARTED | Belum ada alternatif generik / substitusi harga. |
| 53 | Visit pattern AI insight | NOT STARTED | Belum ada. |
| 54 | Konsultasi online / telemedicine | NOT STARTED | Belum ada. |
| 55 | Poin & loyalty pasien | NOT STARTED | Belum ada. |

## Recommended Next Priority

### Priority 1: Close phase 2 operational gaps

1. Corporate / insurance billing layer.
2. Multi-tenant SaaS isolation.
3. Subscription billing SaaS.
4. Patient portal.
5. Analytics dashboard.
6. Medicine price comparison.

### Priority 2: Strengthen clinical and financial workflows

1. Corporate / insurance billing layer.
2. Multi-tenant SaaS isolation.
3. Subscription billing SaaS.
4. Patient portal dan analytics.
5. Drug interaction AI layer.
6. Reorder automation lanjutan.

### Priority 3: SaaS and expansion layer

1. Tenant isolation penuh.
2. Subscription billing SaaS.
3. Patient portal.
4. Analytics dashboard.
5. AI modules.

## Recommended Build Order

1. Corporate / insurance billing layer.
2. Multi-tenant layer.
3. Subscription billing SaaS.
4. Patient portal.
5. Analytics dashboard.
6. Self check-in dan loyalty.
7. AI modules.
