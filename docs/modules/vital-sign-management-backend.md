# Vital Sign Management Backend Notes

## Scope
- Vital signs adalah histori klinis per visit, jadi modul ini tidak memakai soft delete.
- Satu visit boleh memiliki lebih dari satu record vital sign.
- Record vital tidak boleh dipindahkan ke visit lain setelah dibuat.

## Security And Guards
- `view vital sign management` diperlukan untuk membuka halaman dan endpoint index.
- `create vital sign management` diperlukan untuk membuat record.
- `edit vital sign management` diperlukan untuk memperbarui record.
- Vital sign hanya boleh dicatat untuk visit yang:
  - tidak berada di masa depan
  - tidak `cancelled`
  - tidak `completed`
  - tidak `ready_for_checkout`
- Vital sign juga diblok saat medical record visit sudah `final` atau `reopen_requested`.

## Concurrency And Consistency
- Create dan update memakai `DB::transaction()`.
- Visit yang menjadi target vital sign di-lock dengan `lockForUpdate()` untuk menjaga sinkronisasi stage klinis.
- Update record juga memakai `lockForUpdate()` pada row vital sign.
- Create bersifat idempotent untuk payload identik pada visit dan waktu pencatatan yang sama.
- Update bersifat idempotent bila payload tidak menghasilkan perubahan.

## Query Standards
- Filter tervalidasi: `search`, `date`, `branch`, `section`.
- Sorting tervalidasi: `recorded_at`, `created_at`, `systolic_bp`, `temperature_celsius`, `spo2_percent`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Workflow Impact
- Setelah vital sign berhasil dicatat, visit akan ditandai `vital_status = completed`.
- Stage visit akan maju dari `scheduled` atau `waiting_nurse` menjadi `waiting_doctor`.

## Audit Trail
- `create` dan `update` dicatat ke `audit_logs`.
- Snapshot audit menyimpan patient, RM, branch, section, recorder, seluruh metrik vital, dan status visit terkait.

## API And Web Behaviour
- Request JSON menerima response JSON dengan status code yang sesuai.
- Request web tetap menerima redirect dan flash message/error agar kompatibel dengan halaman Blade.
