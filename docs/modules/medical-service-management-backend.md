# Medical Service Management Backend Notes

## Scope
- Modul ini mencakup `medical_services`, `medical_service_branch_prices`, dan `visit_medical_services`.
- `Medical service master` dipakai untuk katalog layanan umum non-procedure seperti observation, nursing service, admin clinical fee, dan layanan lain yang dapat ditagihkan.
- `Service order` adalah transaksi layanan per visit yang memengaruhi billing dan workflow visit.
- Modul ini tidak memakai soft delete. Master diarsipkan lewat `is_active = false`, sedangkan order dibatalkan lewat status `cancelled`.

## Security And Guards
- `view medical service management` diperlukan untuk membuka halaman dan endpoint index.
- `create medical service management` diperlukan untuk membuat master dan order baru.
- `edit medical service management` diperlukan untuk update master dan update order.
- `delete medical service management` diperlukan untuk archive master dan cancel order.
- Service order hanya bisa dibuat untuk visit yang sudah punya medical record.
- Visit dengan `care_stage` `cancelled` atau `completed` tidak bisa dimutasi dari modul ini.
- Master service yang tidak aktif tidak bisa dipakai untuk order baru.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Master, order, dan visit target di-lock dengan `lockForUpdate()`.
- Create/update/archive master bersifat idempotent untuk payload atau state yang sama.
- Update order dan cancel order juga idempotent untuk retry dengan state yang sama.
- Service order tidak boleh dipindahkan ke visit lain setelah dibuat.
- Create order sengaja tidak dipaksa idempotent karena dua order identik tetap bisa valid secara operasional bila memang diminta terpisah.
- Transition status order dibatasi agar tetap konsisten:
  - `ordered -> ordered|completed|cancelled`
  - `completed -> completed`
  - `cancelled -> cancelled`

## Workflow Rules
- Service order `completed` masuk ke billing saat sync visit.
- Service order `cancelled` tidak lagi dihitung ke billing.
- `performed_by_user_id` hanya diisi saat status `completed`.
- Harga order memakai branch override bila ada; jika tidak, fallback ke `default_fee`.

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
