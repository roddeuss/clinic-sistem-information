# Procedure Management Backend Notes

## Scope
- Modul ini mencakup `procedure_masters` dan `visit_procedures`.
- `Procedure master` dipakai sebagai katalog tindakan dengan tarif default dan override per branch.
- `Visit procedure` adalah transaksi tindakan per visit yang memengaruhi workflow visit dan billing.
- Modul ini tidak memakai soft delete. Master diarsipkan lewat `is_active = false`, sedangkan order dibatalkan lewat status `cancelled`.

## Security And Guards
- `view procedure management` diperlukan untuk membuka halaman dan endpoint index.
- `create procedure management` diperlukan untuk membuat master dan order baru.
- `edit procedure management` diperlukan untuk update master dan update order.
- `delete procedure management` diperlukan untuk archive master dan cancel order.
- Visit harus sudah punya medical record sebelum order tindakan bisa dibuat.
- Visit `cancelled` dan `completed` tidak bisa dimutasi dari modul ini.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Master, order, dan visit target di-lock dengan `lockForUpdate()`.
- Create/update master bersifat idempotent untuk payload yang sama.
- Update order dan cancel order juga idempotent untuk retry dengan state yang sama.
- Order tidak boleh dipindahkan ke visit lain setelah dibuat.
- Transition status order dibatasi agar tetap konsisten:
  - `ordered -> ordered|in_progress|completed|cancelled`
  - `in_progress -> in_progress|completed|cancelled`
  - `completed -> completed`
  - `cancelled -> cancelled`

## Workflow Rules
- Tindakan `completed` masuk ke billing saat sync visit.
- Tindakan `cancelled` tidak lagi dihitung ke billing.
- `performer_scope` divalidasi saat status `in_progress` atau `completed`.
- `requires_doctor_order` memaksa visit punya doctor yang valid.
- Admin (`super-admin`, `clinic-admin`) bisa override performer scope untuk koreksi operasional.

## Query Standards
- Filter tervalidasi: `search`, `branch`, `status`, `master_status`, `date`.
- Sorting tervalidasi:
  - master table: `code`, `name`, `created_at`
  - order table: `ordered_at`, `created_at`, `status`, `subtotal`
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `audit_logs` mencatat action:
  - `create_master`
  - `update_master`
  - `archive_master`
  - `create_order`
  - `update_order`
  - `cancel_order`

## API And Web Behaviour
- Request JSON menerima response JSON dan status code yang sesuai (`200`, `201`, `403`, `404`, `409`, `422`).
- Request web tetap menerima redirect dan flash message agar kompatibel dengan Blade.
