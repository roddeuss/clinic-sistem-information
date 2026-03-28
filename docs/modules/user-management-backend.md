# User Management Backend Notes

## Scope
- User management tetap dipisah dari employee.
- User tidak menggunakan soft delete; arsip dilakukan dengan `is_active = false`.
- Semua write operation kritikal memakai `DB::transaction()`.

## Security And Guards
- `view user management` diperlukan untuk membuka halaman/index.
- `create user management` diperlukan untuk membuat akun.
- `edit user management` diperlukan untuk memperbarui akun.
- `delete user management` diperlukan untuk arsip/nonaktifkan akun.
- Akun yang sedang dipakai tidak boleh menonaktifkan dirinya sendiri.
- Akun yang sedang dipakai tidak boleh mengubah role dirinya sendiri dari modul ini.
- Sistem harus selalu menyisakan minimal satu `super-admin` aktif.
- Saat akun dinonaktifkan, seluruh session aktif dan `remember_token` dibersihkan.

## Query Standards
- Filter tervalidasi: `search`, `role`, `status`.
- Sorting tervalidasi: `name`, `email`, `created_at`, `last_login_at`, `is_active`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.
- User index tetap memakai eager loading `employee` dan `roles`.

## Audit Trail
- `create`, `update`, dan `archive` user dicatat ke `audit_logs`.
- Snapshot audit hanya menyimpan field operasional yang aman: nama, email, status, role, employee number, dan last login.

## Concurrency And Consistency
- Update dan archive user melakukan `lockForUpdate()` pada row target.
- Update yang tidak mengubah apa pun dianggap idempotent dan tidak membuat audit log baru.

## API And Web Behaviour
- Request JSON akan menerima response JSON dengan HTTP status yang sesuai.
- Request web tetap mendapat redirect + flash message/error agar kompatibel dengan halaman Blade yang ada.
