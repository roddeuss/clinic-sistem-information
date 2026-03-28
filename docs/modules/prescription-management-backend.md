# Prescription Management Backend Notes

## Scope
- Prescription disimpan satu kali per visit lewat tabel `prescriptions`.
- Item resep, dispensing, dan override interaksi dicatat sebagai histori operasional dan klinis.
- Modul ini tidak memakai soft delete karena histori obat dan dispensing harus tetap utuh; pembatalan dilakukan lewat status seperti `cancelled`, `external`, atau `partial_cancelled`.

## Security And Guards
- `view prescription management` diperlukan untuk membuka halaman, endpoint index, dan print label.
- `create prescription management` diperlukan untuk membuat header prescription dan item baru.
- `edit prescription management` diperlukan untuk update header, update item, finalize, dispense, close remaining, dan override interaksi major.
- `delete prescription management` diperlukan untuk membatalkan item prescription.
- Prescription hanya bisa dibuat untuk visit yang sudah punya SOAP/medical record.
- Visit `cancelled` dan `completed` tidak bisa diubah prescription-nya.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Visit, prescription, dan prescription item yang ditarget di-lock dengan `lockForUpdate()`.
- Create prescription bersifat idempotent untuk retry payload yang sama pada visit yang sama.
- Create/update item, finalize, close remaining, dan override major interaction juga idempotent saat payload yang sama dikirim ulang.
- Dispensing memakai FEFO allocator dan stok baru berkurang setelah dispense berhasil dibuat.
- Item yang sudah punya dispense tidak bisa diubah atau dibatalkan langsung.

## Workflow Rules
- Prescription final membutuhkan minimal satu item.
- Item `external` tidak masuk dispensing in-house.
- `contraindicated` dan alergi berat memblok finalize/dispense.
- `major interaction` wajib dioverride dengan alasan sebelum finalize/dispense.
- `close remaining` hanya berlaku untuk item in-house yang masih punya sisa fulfillment.
- Sinkron billing dan workflow visit dilakukan setelah mutasi penting:
  - create/update/cancel item
  - finalize prescription
  - dispense
  - close remaining

## Query Standards
- Filter tervalidasi: `search`, `branch`, `status`, `date`.
- Sorting tervalidasi:
  - visit table: `visit_date`, `created_at`
  - dispensing table: `finalized_at`, `display_name`, `status`, `created_at`
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `audit_logs` mencatat action:
  - `create`
  - `update`
  - `create_item`
  - `update_item`
  - `cancel_item`
  - `finalize`
  - `dispense`
  - `close_remaining`
  - `override_interaction`
- Snapshot audit menyimpan ringkasan prescription, item, dan dispense agar perubahan mudah ditelusuri.

## API And Web Behaviour
- Request JSON menerima response JSON dan status code yang sesuai (`200`, `201`, `403`, `404`, `409`, `422`).
- Request web tetap menerima redirect dan flash message agar kompatibel dengan Blade.
- Payload JSON untuk index mengembalikan `filters`, `visits`, dan `dispensing_items`.
