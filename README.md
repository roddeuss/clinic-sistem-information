# CSI Clinic HIS

`CSI Clinic HIS` adalah aplikasi **Clinic / Hospital Information System** berbasis Laravel untuk operasional klinik harian.

Fokus aplikasi ini adalah membantu alur end-to-end mulai dari:
- login dan manajemen akses internal
- pengaturan clinic, branch, counter, dan section
- registrasi pasien dan visit
- queue realtime
- vital signs, SOAP, ICD-10, prescription, procedures, diagnostics
- billing, receivable, cashier shift, receipt
- pharmacy, stock, procurement, reorder point, dan safety alert
- referral, doctor letters, notifications, reports, dan backup

Project ini dibangun di atas fondasi starter dashboard Laravel, lalu dikembangkan menjadi sistem operasional klinik yang jauh lebih lengkap.

## Core Use Cases

Aplikasi ini mendukung beberapa role operasional utama:
- `super-admin`
- `clinic-admin`
- `front-office`
- `cashier`
- `doctor`
- `nurse`
- `pharmacist`

Contoh flow yang sudah didukung:
- pasien datang atau booking, lalu dibuat `visit registration`
- sistem generate queue per section
- nurse input vital signs
- doctor isi SOAP, diagnosis ICD-10, prescription, procedures, diagnostics
- pharmacy melakukan dispensing
- cashier menutup billing, receipt, atau receivable
- admin memantau dashboard operasional, procurement, inventory, dan report

## Main Modules

Modul utama yang sudah tersedia antara lain:
- User management
- Role & permission management
- Dynamic menu & menu category
- Clinic, branch, counter, section
- Doctor & doctor schedule
- Patient management
- Visit registration
- Queue realtime
- Vital signs
- Medical record / SOAP
- ICD-10 master
- Prescription & dispensing
- Procedures
- Medical services
- Diagnostics / laboratory / radiology support
- Billing, invoice, payment methods, cashier shift, receivable
- Product categories, suppliers
- Purchase order, goods receipt, purchase return
- Stock adjustment, stock opname, expiry monitoring
- Reorder point & drug interaction safety checks
- Referrals
- Doctor letters
- Notification center
- Reports
- Backup & restore

## Tech Stack

### Backend
- `PHP 8.2+`
- `Laravel 12`
- `PostgreSQL` as primary database target
- `Spatie Laravel Permission` for RBAC
- `Laravel Reverb` for realtime queue broadcasting
- `Barryvdh Laravel DomPDF` for PDF/print documents
- `Maatwebsite Excel` for report export

### Frontend
- `Blade`
- `Tailwind CSS v4`
- `Alpine.js`
- `Vite`
- `Laravel Echo`
- `Pusher JS` client for Reverb-compatible realtime

### Supporting Libraries
- `ApexCharts`
- `Flatpickr`
- `FullCalendar`
- `Swiper`
- `Floating UI`

## Architecture Notes

Project ini memakai pendekatan modular di dalam Laravel:
- `app/Modules/...` untuk controller, request, service, exception per modul
- `app/Models/...` untuk Eloquent model
- `resources/views/modules/...` untuk halaman dashboard per modul
- `docs/modules/...` untuk dokumentasi backend dan hardening notes

Beberapa prinsip yang sudah diterapkan:
- request validation dipisah dari controller
- business logic dipusatkan di service
- transaction dan concurrency handling untuk flow kritikal
- audit log untuk aksi penting
- role-aware dashboard
- dynamic sidebar dari menu category dan menu

## Realtime Features

Realtime saat ini dipakai terutama untuk:
- queue / antrian
- dashboard operasional
- live board / display board

Reverb dijalankan bersama queue worker dan Vite saat mode development.

## Local Development Setup

### Requirements
- PHP `8.2+` (direkomendasikan `8.3`)
- Composer
- Node.js / npm
- PostgreSQL

### Installation

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### Run in Development

```bash
composer run dev
```

Command di atas akan menjalankan:
- Laravel app server
- queue listener
- Laravel Reverb
- Laravel Pail
- Vite dev server

Kalau ingin jalan manual, kamu bisa pisahkan prosesnya:

```bash
php artisan serve
php artisan queue:listen --tries=1
php artisan reverb:start --host=0.0.0.0 --port=8080
npm run dev
```

## Testing

Untuk menjalankan test suite:

```bash
php artisan test
```

Atau lewat Composer:

```bash
composer test
```

Project ini sudah memiliki banyak feature test untuk modul operasional utama.

## Important Notes

- Queue realtime memakai broadcasting Laravel + Reverb.
- Beberapa print output tersedia dalam format `A4` dan `thermal receipt`.
- Region lookup pasien memakai proxy backend ke API referensi eksternal Indonesia.
- Backup saat ini berfokus pada `database backup` dalam format terkompresi.

## Project Goal

Tujuan project ini adalah menjadi fondasi **Clinic Information System** yang tetap:
- mudah dikembangkan
- tidak bergantung ke SPA framework
- cepat dipakai di operasional klinik
- aman dari sisi role, audit, dan alur bisnis

## Credits

Fondasi UI awal berasal dari starter dashboard Laravel berbasis TailAdmin, lalu dikembangkan lebih jauh menjadi aplikasi `CSI Clinic HIS`.
