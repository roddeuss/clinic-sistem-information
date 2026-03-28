# Patient Management Backend Notes

## Scope
- Patient master tetap disimpan sebagai entitas klinis permanen dan tidak memakai soft delete.
- Arsip/nonaktif dilakukan lewat `is_active = false`.
- Nomor RM tetap dipisah ke `patient_branch_records`.
- Semua write operation penting memakai `DB::transaction()`.

## Security And Guards
- `view patient management` diperlukan untuk membuka halaman/index.
- `create patient management` diperlukan untuk membuat patient.
- `edit patient management` diperlukan untuk memperbarui patient.
- `delete patient management` diperlukan untuk menonaktifkan patient.
- Branch awal untuk pembuatan RM harus branch aktif.
- Patient tidak bisa dinonaktifkan jika masih punya visit/antrian aktif dengan `registration_status` selain `completed` atau `cancelled`.

## Query Standards
- Filter tervalidasi: `search`, `branch`, `status`.
- Sorting tervalidasi: `full_name`, `created_at`, `date_of_birth`, `is_active`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.
- Index tetap eager load `branchRecords.branch`.

## Consistency And Idempotency
- Update patient memakai `lockForUpdate()` pada row target.
- Update tanpa perubahan field maupun branch record baru dianggap idempotent dan tidak membuat audit log.
- Pembuatan RM per branch tetap memakai locking di `PatientRecordService`.

## Audit Trail
- `create`, `update`, dan `archive` patient dicatat ke `audit_logs`.
- Snapshot audit sengaja tidak menyimpan alamat lengkap dan NIK utuh; NIK dicatat dalam bentuk masked.

## API And Web Behaviour
- Request JSON menerima response JSON dengan HTTP status yang sesuai.
- Request web tetap menerima redirect + flash message/error agar kompatibel dengan halaman Blade.
