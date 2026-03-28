# Doctor Letter Management Backend Notes

## Scope
- Modul ini mencakup `doctor_letters`.
- Surat dokter berbasis visit dan tetap terhubung ke patient, branch, section, dan doctor snapshot.
- Modul mendukung `surat sakit`, `surat sehat`, `surat kontrol`, dan `surat bebas narkoba`.
- Draft surat memakai soft delete; perubahan setelah issue dilakukan lewat `void` atau `reissue`, bukan edit diam-diam.

## Security And Guards
- `view doctor letter management` diperlukan untuk membuka halaman dan endpoint index.
- `create doctor letter management` diperlukan untuk membuat draft surat.
- `edit doctor letter management` diperlukan untuk update draft surat.
- `delete doctor letter management` diperlukan untuk void dan hapus draft.
- `issue doctor letter management` diperlukan untuk issue dan reissue surat.
- `print doctor letter management` diperlukan untuk membuka print surat.
- Surat hanya bisa dibuat untuk visit yang sudah punya medical record.
- Visit `cancelled` tidak bisa dipakai untuk surat dokter.
- Issue hanya boleh jika SOAP visit `final`.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Visit dan surat target di-lock memakai `lockForUpdate()`.
- Create draft bersifat idempotent untuk payload yang sama pada visit dan tipe surat yang sama.
- Jika masih ada draft lain dengan tipe sama di visit yang sama, create/update diblok agar tidak ada draft ganda liar.
- Update draft, issue, print, dan void juga idempotent untuk retry dengan state yang sama.
- Reissue sengaja tidak idempotent karena selalu menghasilkan nomor surat baru.

## Workflow Rules
- Draft dapat diubah sebelum issue.
- `print` hanya valid untuk surat `issued`.
- `void` hanya valid untuk surat `issued`.
- `reissue` hanya valid untuk surat `issued` atau `voided`.
- `delete` hanya valid untuk draft surat.
- Nomor surat dibuat per branch memakai `BranchDocumentNumberService`.

## Query Standards
- Filter tervalidasi: `search`, `status`, `letter_type`.
- Sorting tervalidasi: `letter_no`, `letter_type`, `status`, `issued_at`, `created_at`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `audit_logs` mencatat action:
  - `create_draft`
  - `update_draft`
  - `issue_letter`
  - `print_letter`
  - `void_letter`
  - `reissue_letter`
  - `delete_draft`

## API And Web Behaviour
- Request JSON menerima response JSON dan status code yang sesuai (`200`, `201`, `403`, `404`, `409`, `422`).
- Request web tetap menerima redirect dan flash message agar kompatibel dengan Blade.
