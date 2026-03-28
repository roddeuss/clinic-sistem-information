# Referral Management Backend Notes

## Scope
- Modul ini mencakup `referral_destinations` dan `patient_referrals`.
- `Referral destination` adalah master tujuan rujukan eksternal seperti rumah sakit, spesialis, atau lab/radiologi.
- `Patient referral` adalah dokumen rujukan berbasis visit yang punya medical record.
- `patient_referrals` memakai soft delete untuk draft yang dibatalkan sebelum issue. Destination tidak memakai soft delete; archive dilakukan lewat `is_active = false`.

## Security And Guards
- `view referral management` diperlukan untuk membuka halaman dan endpoint index.
- `create referral management` diperlukan untuk membuat tujuan dan draft referral.
- `edit referral management` diperlukan untuk update tujuan dan draft referral.
- `delete referral management` diperlukan untuk archive tujuan, void referral, dan hapus draft referral.
- `issue referral management` diperlukan untuk issue dan reissue dokumen.
- `print referral management` diperlukan untuk membuka print referral.
- Tujuan referral yang tidak aktif tidak bisa dipakai untuk draft baru.
- Visit yang dibatalkan tidak bisa dipakai untuk dokumen referral.
- Referral hanya bisa di-issue jika SOAP visit sudah `final`.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Destination, referral, dan visit target di-lock memakai `lockForUpdate()`.
- Create/update/archive destination bersifat idempotent untuk payload atau state yang sama.
- Update draft referral, issue, print, dan void juga idempotent untuk retry dengan state yang sama.
- Reissue referral sengaja tidak dibuat idempotent karena setiap reissue menghasilkan nomor dokumen baru.
- Draft referral bisa diubah sebelum issue. Setelah issue, perubahan dilakukan lewat `void` atau `reissue`, bukan edit diam-diam.

## Workflow Rules
- Draft referral bisa dibuat untuk visit yang sudah punya medical record.
- Dokumen final memakai nomor referral per branch dari `BranchDocumentNumberService`.
- `print` hanya valid untuk referral `issued`.
- `void` hanya valid untuk referral `issued`.
- `reissue` hanya valid untuk referral `issued` atau `voided`.
- `delete` hanya valid untuk draft referral.

## Query Standards
- Filter tervalidasi:
  - destinations: `destination_search`, `destination_status`, `destination_type`
  - referrals: `referral_search`, `referral_status`
- Sorting tervalidasi:
  - destinations: `code`, `name`, `destination_type`, `created_at`
  - referrals: `referral_no`, `status`, `issued_at`, `created_at`
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- `audit_logs` mencatat action:
  - `create_destination`
  - `update_destination`
  - `archive_destination`
  - `create_referral_draft`
  - `update_referral_draft`
  - `issue_referral`
  - `print_referral`
  - `void_referral`
  - `reissue_referral`
  - `delete_referral_draft`

## API And Web Behaviour
- Request JSON menerima response JSON dan status code yang sesuai (`200`, `201`, `403`, `404`, `409`, `422`).
- Request web tetap menerima redirect dan flash message agar kompatibel dengan Blade.
