# Role Permission Backend Notes

## Scope
- Role management memakai request khusus, service khusus, dan controller tipis.
- `system role` dari konfigurasi hanya bisa di-update permission-nya, tidak bisa dihapus.
- `custom role` hanya bisa dihapus jika tidak dipakai oleh user mana pun.
- Soft delete tidak dipakai pada `roles` karena tabel package permission tidak dirancang untuk lifecycle soft delete.

## Security And Guards
- `view role permission` diperlukan untuk membuka halaman/index.
- `create role permission` diperlukan untuk membuat role.
- `edit role permission` diperlukan untuk mengubah permission role.
- `delete role permission` diperlukan untuk menghapus custom role.

## Query Standards
- Filter tervalidasi: `search`, `scope`.
- Sorting tervalidasi: `name`, `created_at`, `permissions_count`, `users_count`.
- Pagination tervalidasi: `10`, `25`, `50`, `100`.
- Role list memakai eager loading `permissions` dan `withCount()` untuk `permissions` dan `users`.

## Consistency And Concurrency
- Semua write operation memakai `DB::transaction()`.
- Update permission role memakai `lockForUpdate()` pada row role.
- Delete role juga memeriksa pivot assignment user secara transaksional sebelum benar-benar menghapus role.
- Update permission yang sama dianggap idempotent dan tidak membuat audit log baru.

## Audit Trail
- `create`, `update`, dan `delete` role dicatat ke `audit_logs`.
- Snapshot audit menyimpan nama role, guard, daftar permission, jumlah permission, jumlah user, dan flag system/custom.

## API And Web Behaviour
- Request JSON menerima response JSON dengan HTTP status yang sesuai.
- Request web tetap menerima redirect + flash message/error untuk kompatibilitas dengan halaman Blade.
