# Diagnostics Management Backend Notes

## Scope
- Modul ini mencakup `diagnostics test master` dan `diagnostics order` yang saat ini memakai tabel `laboratory_*`.
- `Diagnostics test` dipakai untuk laboratory, radiology, dan support diagnostics lain.
- `Diagnostics order` adalah transaksi pemeriksaan penunjang per visit yang memengaruhi billing, workflow visit, print request, print result, dan notification center.
- Modul ini tidak memakai soft delete. Master diarsipkan lewat `is_active = false`, sedangkan order dibatalkan lewat status `cancelled`.

## Security And Guards
- `view laboratory management` diperlukan untuk membuka halaman, endpoint index, dan print.
- `create laboratory management` diperlukan untuk membuat master dan order baru.
- `edit laboratory management` diperlukan untuk update master dan update order.
- `delete laboratory management` diperlukan untuk archive master dan cancel order.
- Diagnostics order hanya bisa dibuat untuk visit yang sudah punya medical record.
- Visit `cancelled` dan `completed` tidak bisa dimutasi dari modul ini.
- Diagnostic test yang tidak aktif tidak bisa dipakai untuk order baru.
- Status `reviewed` hanya bisa disimpan oleh `doctor`, `clinic-admin`, atau `super-admin`.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Test master, order, dan visit target di-lock dengan `lockForUpdate()`.
- Create/update/archive test bersifat idempotent untuk payload atau state yang sama.
- Update order, cancel order, print request, dan print result juga idempotent untuk retry dengan state yang sama.
- Order tidak boleh dipindahkan ke visit lain setelah dibuat.
- Create order sengaja tidak dipaksa idempotent karena dua order identik masih bisa valid secara operasional bila memang diminta terpisah.
- Transition status order dibatasi agar tetap konsisten:
  - `ordered -> ordered|sample_collected|processing|sent_to_partner|resulted|reviewed|cancelled`
  - `sample_collected -> sample_collected|processing|resulted|reviewed|cancelled`
  - `processing -> processing|resulted|reviewed|cancelled`
  - `sent_to_partner -> sent_to_partner|resulted|reviewed|cancelled`
  - `resulted -> resulted|reviewed`
  - `reviewed -> reviewed`
  - `cancelled -> cancelled`

## Workflow Rules
- Provider `internal` hanya boleh memakai status:
  - `ordered`, `sample_collected`, `processing`, `resulted`, `reviewed`, `cancelled`
- Provider `external` hanya boleh memakai status:
  - `ordered`, `sent_to_partner`, `resulted`, `reviewed`, `cancelled`
- Untuk provider `external`, `partner_name` wajib diisi.
- Untuk status `resulted` atau `reviewed`:
  - test `structured` wajib punya `result_lines`
  - test `narrative` wajib punya `result_summary`, `result_impression`, atau attachment
  - test `hybrid` wajib punya structured result atau narrative result
- Result print hanya boleh saat status `reviewed`.
- Saat status berubah ke `reviewed`, notification center mengirim alert ke role operasional terkait.

## Query Standards
- Filter tervalidasi: `search`, `branch`, `status`, `provider_type`, `date`, `test_status`.
- Sorting tervalidasi:
  - test table: `code`, `name`, `diagnostic_category`, `created_at`
  - order table: `ordered_at`, `created_at`, `status`, `unit_price`
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `audit_logs` mencatat action:
  - `create_test`
  - `update_test`
  - `archive_test`
  - `create_order`
  - `update_order`
  - `cancel_order`
  - `print_request`
  - `print_result`

## API And Web Behaviour
- Request JSON menerima response JSON dan status code yang sesuai (`200`, `201`, `403`, `404`, `409`, `422`).
- Request web tetap menerima redirect dan flash message agar kompatibel dengan Blade.
