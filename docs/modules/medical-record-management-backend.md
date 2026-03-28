# Medical Record Management Backend Notes

## Scope
- Medical record disimpan satu kali per visit lewat tabel `medical_records`.
- Modul ini tidak memakai soft delete karena rekam medis adalah histori klinis legal.
- Perubahan setelah final dilakukan melalui flow `request reopen`, `direct reopen`, atau `approve reopen`.

## Security And Guards
- `view medical record management` diperlukan untuk membuka halaman dan endpoint index.
- `create medical record management` diperlukan untuk membuat SOAP baru.
- `edit medical record management` diperlukan untuk mengubah SOAP draft/reopened.
- `approve reopen` hanya untuk `super-admin` dan `clinic-admin`.
- Dokter hanya boleh request reopen pada record `final`.
- Dokter yang dipilih harus aktif dan terhubung ke section visit yang sama.

## Concurrency And Consistency
- Semua write action memakai `DB::transaction()`.
- Visit dan medical record yang ditarget di-lock dengan `lockForUpdate()`.
- Create bersifat idempotent untuk retry payload yang sama pada visit yang sama.
- Update bersifat idempotent bila tidak ada perubahan data maupun perubahan status submit.
- Record tidak boleh dipindahkan ke visit lain setelah dibuat.

## Workflow Rules
- Visit masa depan, visit `cancelled`, dan visit `completed` tidak bisa diubah rekam medisnya.
- Record `final` dan `reopen_requested` tidak bisa diedit langsung.
- Submit `final` akan:
  - mencatat audit domain `finalized`
  - sinkron ke billing
  - refresh workflow visit ke `awaiting_fulfillment`, `ready_for_checkout`, atau `completed`
- Submit `draft` menjaga visit pada `in_consultation`.
- `request reopen` dan `approve/direct reopen` juga refresh workflow visit ke `in_consultation`.

## Query Standards
- Filter tervalidasi: `search`, `date`, `branch`, `section`, `status`.
- Sorting tervalidasi: `visit_date`, `created_at`, `care_stage`, `vital_status`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.

## Audit Trail
- Audit domain tetap dicatat ke `medical_record_audits`.
- Audit operasional umum dicatat ke `audit_logs` untuk action:
  - `create`
  - `update`
  - `request_reopen`
  - `direct_reopen`
  - `approve_reopen`

## API And Web Behaviour
- Request JSON menerima response JSON dan status code yang sesuai (`200`, `201`, `403`, `404`, `409`, `422`).
- Request web tetap menerima redirect dan flash message agar kompatibel dengan Blade.
