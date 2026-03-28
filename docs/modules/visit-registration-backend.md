# Visit Registration Backend Notes

## Scope
- Visit registration adalah data klinis permanen, jadi tidak memakai soft delete.
- Operasi "hapus" diganti menjadi `cancel`, sehingga jejak registrasi tetap ada.
- Semua write operation kritikal memakai `DB::transaction()`.

## Security And Guards
- `view visit registration` diperlukan untuk membuka halaman dan availability endpoint.
- `create visit registration` diperlukan untuk membuat registrasi.
- `edit visit registration` diperlukan untuk update registrasi dan check-in booking.
- `delete visit registration` diperlukan untuk cancel registrasi.
- Registrasi hanya bisa dikelola dari branch counter aktif yang sama.
- Patient harus aktif.
- Section harus aktif dan berada pada branch counter aktif.

## Slot And Concurrency
- Create/update tidak hanya percaya payload frontend; slot dokter diverifikasi ulang di backend.
- Backend melakukan lock pada `doctor_schedules`, `doctor_leaves`, dan registrasi konflik untuk slot yang dipilih.
- Registrasi yang sudah punya antrian aktif atau sudah completed tidak bisa diedit dari modul ini.
- Check-in booking idempotent: booking yang sudah punya queue aktif tidak akan membuat queue baru lagi.

## Query Standards
- Filter tervalidasi: `search`, `date`, `status`, `type`, `section`.
- Sorting tervalidasi: `visit_date`, `created_at`, `registration_status`, `visit_type`, `checked_in_at`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `create`, `update`, `check_in`, dan `cancel` dicatat ke `audit_logs`.
- Snapshot audit menyimpan identitas operasional registrasi, termasuk patient, RM, section, doctor, status, queue code, dan waktu penting.

## API And Web Behaviour
- Request JSON menerima response JSON dengan HTTP status yang sesuai.
- Request web tetap menerima redirect + flash message/error agar kompatibel dengan halaman Blade.
