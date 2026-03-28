# Queue Management Backend Notes

## Scope
- Queue ticket adalah data operasional yang juga menjadi histori layanan, jadi modul ini tidak memakai soft delete.
- Operasi "hapus" diganti menjadi status transition seperti `cancelled` atau `completed`.
- Semua write action penting memakai `DB::transaction()`.

## Security And Guards
- `view queue management` diperlukan untuk membuka halaman queue desk, display, dan endpoint board.
- `edit queue management` diperlukan untuk `call next` dan semua transition queue.
- Semua action queue dibatasi pada branch dari counter aktif.
- `call next` hanya boleh berjalan jika section tidak sedang memiliki queue aktif lain dengan status `called` atau `in_service`.

## Concurrency And Idempotency
- Queue action memakai `lockForUpdate()` pada `queue_tickets`, `visit_registrations`, dan pengecekan active queue per section.
- `call`, `serve`, `complete`, `skip`, dan `cancel` bersifat idempotent untuk aksi yang sama:
  - memanggil queue yang sudah `called` tidak akan mengubah data lagi
  - `serve` pada queue `in_service` tidak akan menulis ulang
  - `complete` pada queue `completed` tidak akan menulis ulang
  - `skip` pada queue `skipped` tidak akan menulis ulang
  - `cancel` pada queue `cancelled` tidak akan menulis ulang
- Invalid transition tetap diblok dengan error bisnis yang jelas.

## Query Standards
- Filter tervalidasi: `search`, `date`, `status`, `section`.
- Sorting tervalidasi: `queue_date`, `queue_number`, `status`, `called_at`, `completed_at`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `call_next` dan semua `transition_*` dicatat ke `audit_logs`.
- Snapshot audit menyimpan patient, RM, branch, section, doctor, counter, queue code, status, dan timestamp penting.

## API And Web Behaviour
- Request JSON menerima response JSON dengan status code yang sesuai.
- Request web tetap menerima redirect dan flash message agar kompatibel dengan halaman Blade.
