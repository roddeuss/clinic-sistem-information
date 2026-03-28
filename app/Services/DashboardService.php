<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashierShift;
use App\Models\Doctor;
use App\Models\DoctorLetter;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LaboratoryOrder;
use App\Models\MedicalRecord;
use App\Models\MedicineBatch;
use App\Models\MedicineReorderPolicy;
use App\Models\PatientReferral;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use App\Models\PurchaseOrder;
use App\Models\QueueTicket;
use App\Models\Receivable;
use App\Models\User;
use App\Models\VisitProcedure;
use App\Models\VisitRegistration;
use App\Models\VitalSignRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class DashboardService
{
    public function __construct(
        private readonly ActiveCounterService $activeCounterService,
        private readonly CashierShiftSessionService $cashierShiftSessionService,
        private readonly QueueBoardService $queueBoardService,
        private readonly NotificationCenterService $notificationCenterService,
    ) {
    }

    public function getOverview(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        $roleKey = $this->resolveDashboardRole($user);
        $roleLabel = $user?->getRoleNames()->isNotEmpty()
            ? config('csi_access.roles.' . $user->getRoleNames()->first() . '.label', $user->getRoleNames()->first())
            : 'Belum ada role';

        $activeCounter = $this->activeCounterService->activeCounter();
        $activeShift = $this->cashierShiftSessionService->activeShift();
        $doctorProfile = $this->resolveDoctorProfile($user);
        $branch = $this->resolveBranchContext($roleKey, $activeCounter?->branch, $activeShift?->branch, $doctorProfile);
        $notificationSummary = $this->notificationCenterService->getHeaderData($user, 5);

        $payload = match ($roleKey) {
            'clinic-admin', 'super-admin' => $this->clinicAdminPayload($user, $branch),
            'front-office' => $this->frontOfficePayload($user, $branch),
            'cashier' => $this->cashierPayload($user, $branch, $activeShift),
            'doctor' => $this->doctorPayload($user, $doctorProfile, $branch),
            'nurse' => $this->nursePayload($user, $branch),
            'pharmacist' => $this->pharmacistPayload($user, $branch),
            default => $this->fallbackPayload($user, $branch),
        };

        return [
            'dashboardRole' => $roleKey,
            'dashboardHeader' => $this->dashboardHeader($roleKey, $roleLabel, $user),
            'dashboardContext' => [
                'role_label' => $roleLabel,
                'role_key' => $roleKey,
                'branch' => $branch,
                'activeCounter' => $activeCounter,
                'activeShift' => $activeShift,
                'doctorProfile' => $doctorProfile,
                'today_label' => now()->translatedFormat('l, d F Y'),
                'chips' => $this->contextChips($roleLabel, $branch, $activeCounter, $activeShift, $notificationSummary['unreadCount']),
                'showCounterSelector' => $this->shouldShowCounterSelector($user),
            ],
            'counterOptions' => $this->activeCounterService->options(),
            'summaryCards' => $payload['summaryCards'],
            'quickActions' => $payload['quickActions'],
            'mainPanel' => $payload['mainPanel'],
            'attentionPanel' => $payload['attentionPanel'],
            'queuePanel' => $payload['queuePanel'],
            'notificationsPanel' => [
                'title' => 'Notifikasi Penting',
                'description' => 'Semua alert lintas modul yang perlu ditindak dari dashboard.',
                'unreadCount' => $notificationSummary['unreadCount'],
                'items' => $this->notificationItems($notificationSummary['recentNotifications']),
                'route' => route('notifications'),
            ],
        ];
    }

    private function clinicAdminPayload(?User $user, ?Branch $branch): array
    {
        $today = now()->toDateString();

        $visitsToday = VisitRegistration::query()
            ->whereDate('visit_date', $today)
            ->where('registration_status', '!=', 'cancelled')
            ->count();

        $waitingQueues = QueueTicket::query()
            ->whereDate('queue_date', $today)
            ->where('status', 'waiting')
            ->count();

        $paymentsToday = (float) InvoicePayment::query()
            ->whereDate('payment_date', $today)
            ->sum('amount');

        $lowStockItems = $this->lowStockCount(null);
        $pendingPo = PurchaseOrder::query()->where('status', 'submitted')->count();
        $dueReceivables = Receivable::query()
            ->whereIn('status', ['open', 'overdue'])
            ->whereDate('due_date', '<=', $today)
            ->count();
        $diagnosticReview = LaboratoryOrder::query()->where('status', 'resulted')->count();
        $reopenRequests = MedicalRecord::query()->where('status', 'reopen_requested')->count();
        $openShifts = CashierShift::query()->where('status', 'open')->count();
        $expiringSoon = $this->expiringBatchCount(null, 30);

        return [
            'summaryCards' => [
                $this->summaryCard('Visit Hari Ini', $this->formatNumber($visitsToday), 'Registrasi aktif lintas branch untuk tanggal hari ini.', 'bg-sky-500'),
                $this->summaryCard('Queue Menunggu', $this->formatNumber($waitingQueues), 'Jumlah antrian waiting yang belum dipanggil dari seluruh branch.', 'bg-amber-500'),
                $this->summaryCard('Revenue Hari Ini', $this->formatCurrency($paymentsToday), 'Akumulasi pembayaran yang benar-benar masuk hari ini.', 'bg-emerald-500'),
                $this->summaryCard('Low Stock Items', $this->formatNumber($lowStockItems), 'Policy reorder aktif yang sudah menyentuh ambang stok minimum.', 'bg-rose-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Visit Desk', route('visit-registrations'), 'Pantau registrasi, booking, dan check-in pasien.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
                $this->quickAction('Queue Desk', route('queues'), 'Lihat mini board antrian dan panggil pasien per section.', 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-300'),
                $this->quickAction('Billing', route('billing'), 'Kontrol invoice, payment, dan receipt harian.', 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'),
                $this->quickAction('Purchase Orders', route('purchase-orders'), 'Review PO yang perlu approval atau follow-up supplier.', 'from-violet-500/15 to-violet-500/5 text-violet-700 dark:text-violet-300'),
                $this->quickAction('Reports', route('reports'), 'Buka laporan operasional dan ringkasan bisnis klinik.', 'from-brand-500/15 to-brand-500/5 text-brand-700 dark:text-brand-300'),
                $this->quickAction('Notifications', route('notifications'), 'Lihat semua alert lintas modul tanpa pindah-pindah halaman.', 'from-rose-500/15 to-rose-500/5 text-rose-700 dark:text-rose-300'),
            ]),
            'mainPanel' => [
                'title' => 'Tugas Prioritas Hari Ini',
                'description' => 'Area yang paling berpengaruh ke arus pasien, approval, dan cashflow klinik.',
                'emptyMessage' => 'Tidak ada pekerjaan mendesak di dashboard admin saat ini.',
                'items' => [
                    $this->listItem('PO menunggu approval', $this->metaLine($pendingPo, 'dokumen', 'Butuh keputusan admin sebelum proses pembelian lanjut.'), $this->statusPill($this->formatNumber($pendingPo), $pendingPo > 0 ? 'warning' : 'success'), route('purchase-orders')),
                    $this->listItem('Receivable jatuh tempo', $this->metaLine($dueReceivables, 'invoice', 'Tagihan yang perlu follow-up pembayaran hari ini.'), $this->statusPill($this->formatNumber($dueReceivables), $dueReceivables > 0 ? 'danger' : 'success'), route('receivables')),
                    $this->listItem('Hasil diagnostik perlu review', $this->metaLine($diagnosticReview, 'order', 'Order sudah resulted tapi belum reviewed.'), $this->statusPill($this->formatNumber($diagnosticReview), $diagnosticReview > 0 ? 'warning' : 'success'), route('laboratory', ['status' => 'resulted'])),
                    $this->listItem('Request reopen SOAP', $this->metaLine($reopenRequests, 'record', 'Medical record yang menunggu keputusan reopen.'), $this->statusPill($this->formatNumber($reopenRequests), $reopenRequests > 0 ? 'warning' : 'success'), route('medical-records', ['status' => 'reopen_requested'])),
                ],
            ],
            'attentionPanel' => [
                'title' => 'Kontrol Operasional',
                'description' => 'Ringkasan area yang biasanya perlu monitoring rutin dari clinic admin.',
                'items' => [
                    $this->attentionItem('Cashier shift open', $this->formatNumber($openShifts), 'Shift kasir yang masih aktif di seluruh branch.', route('cashier-shifts'), 'info'),
                    $this->attentionItem('Batch expiring <= 30 hari', $this->formatNumber($expiringSoon), 'Batch aktif yang harus segera dipantau atau diprioritaskan keluar.', route('expiry-monitoring'), $expiringSoon > 0 ? 'warning' : 'success'),
                    $this->attentionItem('Invoice belum lunas', $this->formatNumber($this->openInvoiceCount(null)), 'Invoice unpaid atau partial paid yang masih perlu tindakan.', route('billing'), 'danger'),
                ],
            ],
            'queuePanel' => $this->queuePanelData($branch),
        ];
    }

    private function frontOfficePayload(?User $user, ?Branch $branch): array
    {
        $today = now()->toDateString();
        $branchId = $branch?->id;

        $registrationsToday = $this->visitsForBranch($branchId)
            ->whereDate('visit_date', $today)
            ->where('registration_status', '!=', 'cancelled')
            ->count();

        $bookingArrivals = $this->visitsForBranch($branchId)
            ->whereDate('visit_date', $today)
            ->where('visit_type', 'booking')
            ->where('registration_status', 'booked')
            ->count();

        $waitingQueue = $this->queuesForBranch($branchId)
            ->whereDate('queue_date', $today)
            ->where('status', 'waiting')
            ->count();

        $waitingNurse = $this->visitsForBranch($branchId)
            ->whereDate('visit_date', $today)
            ->where('care_stage', 'waiting_nurse')
            ->count();

        $arrivals = $this->visitsForBranch($branchId)
            ->with(['patient:id,full_name', 'section:id,name', 'doctor:id,full_name,title_prefix,title_suffix', 'doctorSchedule:id,room_label'])
            ->whereDate('visit_date', $today)
            ->where('registration_status', '!=', 'cancelled')
            ->orderByRaw("CASE registration_status WHEN 'booked' THEN 0 WHEN 'queued' THEN 1 WHEN 'called' THEN 2 ELSE 3 END")
            ->orderBy('slot_start_time')
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        return [
            'summaryCards' => [
                $this->summaryCard('Registrasi Hari Ini', $this->formatNumber($registrationsToday), 'Total visit yang masuk di branch fokus hari ini.', 'bg-sky-500'),
                $this->summaryCard('Booking Perlu Check-in', $this->formatNumber($bookingArrivals), 'Booking tanggal hari ini yang masih menunggu kedatangan pasien.', 'bg-amber-500'),
                $this->summaryCard('Queue Menunggu', $this->formatNumber($waitingQueue), 'Jumlah pasien waiting di board antrian branch fokus.', 'bg-brand-500'),
                $this->summaryCard('Menuju Nurse Station', $this->formatNumber($waitingNurse), 'Pasien yang sudah queued dan menunggu intake awal.', 'bg-emerald-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Patients', route('patients'), 'Cari pasien lama atau buat data pasien baru dengan cepat.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
                $this->quickAction('Visit Registration', route('visit-registrations'), 'Buat same-day visit, booking, atau check-in booking.', 'from-brand-500/15 to-brand-500/5 text-brand-700 dark:text-brand-300'),
                $this->quickAction('Queue Desk', route('queues'), 'Pantau antrian section dan status pasien berjalan.', 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-300'),
                $this->quickAction('Billing', route('billing'), 'Akses invoice pasien yang sudah siap checkout.', 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'),
                $this->quickAction('Referrals', route('referrals'), 'Issue surat rujukan eksternal dari visit yang sudah siap.', 'from-violet-500/15 to-violet-500/5 text-violet-700 dark:text-violet-300'),
                $this->quickAction('Doctor Letters', route('doctor-letters'), 'Cetak surat sakit, sehat, atau kontrol tanpa keluar alur layanan.', 'from-rose-500/15 to-rose-500/5 text-rose-700 dark:text-rose-300'),
            ]),
            'mainPanel' => [
                'title' => 'Arrivals & Follow-up Hari Ini',
                'description' => 'Daftar pasien yang paling mungkin perlu tindakan cepat dari front office.',
                'emptyMessage' => 'Belum ada arrival yang perlu di-follow-up untuk branch fokus saat ini.',
                'items' => $arrivals->map(fn (VisitRegistration $visit) => $this->listItem(
                    $visit->patient?->full_name ?? 'Pasien',
                    collect([
                        $visit->section?->name,
                        $visit->slot_start_time ? substr((string) $visit->slot_start_time, 0, 5) : 'Walk-in',
                        $visit->doctor?->displayName(),
                    ])->filter()->implode(' | '),
                    $this->statusPill(Str::headline(str_replace('_', ' ', $visit->registration_status)), $this->statusTone($visit->registration_status)),
                    route('visit-registrations')
                ))->all(),
            ],
            'attentionPanel' => [
                'title' => 'Fokus Front Office',
                'description' => 'Shortcut keputusan kecil yang sering mempengaruhi kecepatan alur pendaftaran.',
                'items' => [
                    $this->attentionItem('Counter aktif', $this->contextValue($this->activeCounterService->activeCounter()?->code, 'Belum dipilih'), 'Pilih counter aktif agar branch context dan antrian sinkron.', route('visit-registrations'), $this->activeCounterService->activeCounter() ? 'success' : 'warning'),
                    $this->attentionItem('Surat & referral draft', $this->formatNumber($this->draftDocumentCount()), 'Dokumen yang masih draft dan bisa jadi perlu issue dari front office.', route('doctor-letters'), 'info'),
                    $this->attentionItem('Notifikasi unread', $this->formatNumber($user?->unreadNotifications()->count() ?? 0), 'Alert baru dari modul-modul klinik yang perlu kamu buka.', route('notifications'), 'warning'),
                ],
            ],
            'queuePanel' => $this->queuePanelData($branch),
        ];
    }

    private function cashierPayload(?User $user, ?Branch $branch, ?CashierShift $activeShift): array
    {
        $today = now()->toDateString();
        $branchId = $branch?->id;

        $openInvoices = $this->invoicesForBranch($branchId)
            ->whereIn('status', ['unpaid', 'partial_paid'])
            ->count();

        $paymentsToday = InvoicePayment::query()
            ->when($activeShift?->id, fn (Builder $query) => $query->where('cashier_shift_id', $activeShift->id))
            ->when(! $activeShift?->id && $branchId, fn (Builder $query) => $query->whereHas('invoice', fn (Builder $invoiceQuery) => $invoiceQuery->where('branch_id', $branchId)))
            ->whereDate('payment_date', $today)
            ->sum('amount');

        $dueReceivables = Receivable::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['open', 'overdue'])
            ->whereDate('due_date', '<=', $today)
            ->count();

        $partialInvoices = $this->invoicesForBranch($branchId)
            ->where('status', 'partial_paid')
            ->count();

        $invoiceItems = $this->invoicesForBranch($branchId)
            ->with(['patient:id,full_name', 'patientBranchRecord:id,medical_record_no'])
            ->whereIn('status', ['unpaid', 'partial_paid'])
            ->orderByRaw("CASE status WHEN 'partial_paid' THEN 0 ELSE 1 END")
            ->orderByDesc('issued_at')
            ->limit(6)
            ->get();

        return [
            'summaryCards' => [
                $this->summaryCard('Invoice Perlu Tindakan', $this->formatNumber($openInvoices), 'Invoice unpaid atau partial paid di branch fokus.', 'bg-rose-500'),
                $this->summaryCard('Pembayaran Hari Ini', $this->formatCurrency((float) $paymentsToday), 'Total payment yang masuk di shift/branch fokus hari ini.', 'bg-emerald-500'),
                $this->summaryCard('Receivable Jatuh Tempo', $this->formatNumber($dueReceivables), 'Tagihan tempo yang sudah perlu ditutup atau di-follow-up.', 'bg-amber-500'),
                $this->summaryCard('Partial Paid', $this->formatNumber($partialInvoices), 'Invoice yang sudah mulai dibayar tapi belum lunas.', 'bg-sky-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Billing Desk', route('billing'), 'Terima payment, buat tempo, dan cetak invoice atau receipt.', 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'),
                $this->quickAction('Receivables', route('receivables'), 'Kelola invoice tempo, extend due date, atau settle lunas.', 'from-rose-500/15 to-rose-500/5 text-rose-700 dark:text-rose-300'),
                $this->quickAction('Cashier Shifts', route('cashier-shifts'), 'Buka atau tutup shift serta cek saldo pembukaan.', 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-300'),
                $this->quickAction('Visit Desk', route('visit-registrations'), 'Bantu follow-up check-in dan alur pasien yang belum selesai checkout.', 'from-brand-500/15 to-brand-500/5 text-brand-700 dark:text-brand-300'),
                $this->quickAction('Queue Desk', route('queues'), 'Pantau posisi antrian yang berdampak ke pembayaran dan serah obat.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
            ]),
            'mainPanel' => [
                'title' => 'Invoice Perlu Tindakan',
                'description' => 'Daftar invoice yang paling sering dibutuhkan saat kasir sedang berjaga.',
                'emptyMessage' => 'Belum ada invoice unpaid atau partial paid di branch fokus.',
                'items' => $invoiceItems->map(fn (Invoice $invoice) => $this->listItem(
                    $invoice->invoice_no,
                    collect([
                        $invoice->patient?->full_name,
                        $invoice->patientBranchRecord?->medical_record_no,
                        $this->formatCurrency((float) $invoice->total_amount),
                    ])->filter()->implode(' | '),
                    $this->statusPill(Str::headline(str_replace('_', ' ', $invoice->status)), $this->statusTone($invoice->status)),
                    route('billing')
                ))->all(),
            ],
            'attentionPanel' => [
                'title' => 'Cashier Control',
                'description' => 'Context kerja yang paling berpengaruh ke transaksi kasir saat ini.',
                'items' => [
                    $this->attentionItem('Shift aktif', $this->contextValue($activeShift?->shift_code, 'Belum dibuka'), $activeShift ? 'Saldo buka ' . $this->formatCurrency((float) $activeShift->opening_balance) : 'Buka shift terlebih dahulu sebelum menerima payment.', route('cashier-shifts'), $activeShift ? 'success' : 'warning'),
                    $this->attentionItem('Counter aktif', $this->contextValue($this->activeCounterService->activeCounter()?->code, 'Belum dipilih'), 'Context counter membantu menjaga branch transaksi tetap konsisten.', route('visit-registrations'), $this->activeCounterService->activeCounter() ? 'success' : 'warning'),
                    $this->attentionItem('Notifikasi unread', $this->formatNumber($user?->unreadNotifications()->count() ?? 0), 'Lihat alert invoice, receivable, atau perubahan status penting dari modul lain.', route('notifications'), 'info'),
                ],
            ],
            'queuePanel' => $this->queuePanelData($branch),
        ];
    }

    private function doctorPayload(?User $user, ?Doctor $doctor, ?Branch $branch): array
    {
        $today = now()->toDateString();
        $doctorId = $doctor?->id;

        $todayVisits = $this->doctorVisits($doctorId)
            ->whereDate('visit_date', $today)
            ->where('registration_status', '!=', 'cancelled');

        $visitCount = (clone $todayVisits)->count();
        $waitingForDoctor = (clone $todayVisits)->where('care_stage', 'waiting_doctor')->count();
        $openNotes = MedicalRecord::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->whereIn('status', ['draft', 'reopened', 'reopen_requested'])
            ->count();
        $resultedDiagnostics = LaboratoryOrder::query()
            ->when($doctorId, fn (Builder $query) => $query->where('ordered_by_doctor_id', $doctorId))
            ->where('status', 'resulted')
            ->count();
        $draftPrescriptions = Prescription::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->where('status', 'draft')
            ->count();
        $draftReferrals = PatientReferral::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->where('status', 'draft')
            ->count();
        $draftLetters = DoctorLetter::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId))
            ->where('status', 'draft')
            ->count();

        $patientList = (clone $todayVisits)
            ->with(['patient:id,full_name', 'section:id,name', 'doctorSchedule:id,room_label'])
            ->orderByRaw('CASE WHEN slot_start_time IS NULL THEN 1 ELSE 0 END')
            ->orderBy('slot_start_time')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'summaryCards' => [
                $this->summaryCard('Pasien Saya Hari Ini', $this->formatNumber($visitCount), 'Total visit yang diarahkan ke dokter ini untuk hari ini.', 'bg-sky-500'),
                $this->summaryCard('Menunggu Saya', $this->formatNumber($waitingForDoctor), 'Visit yang sudah selesai intake dan siap masuk konsultasi.', 'bg-amber-500'),
                $this->summaryCard('Catatan Belum Final', $this->formatNumber($openNotes), 'SOAP draft, reopened, atau reopen_requested yang masih terbuka.', 'bg-brand-500'),
                $this->summaryCard('Hasil Perlu Review', $this->formatNumber($resultedDiagnostics), 'Order diagnostik yang sudah resulted dan menunggu review.', 'bg-emerald-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Medical Records', route('medical-records'), 'Lanjutkan SOAP, diagnosis, dan finalisasi kunjungan.', 'from-brand-500/15 to-brand-500/5 text-brand-700 dark:text-brand-300'),
                $this->quickAction('Prescriptions', route('prescriptions'), 'Tulis resep, cek interaction, dan finalisasi order obat.', 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'),
                $this->quickAction('Diagnostics', route('laboratory'), 'Order penunjang dan review hasil yang sudah masuk.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
                $this->quickAction('Referrals', route('referrals'), 'Buat surat rujukan eksternal bila pasien perlu tindak lanjut.', 'from-violet-500/15 to-violet-500/5 text-violet-700 dark:text-violet-300'),
                $this->quickAction('Doctor Letters', route('doctor-letters'), 'Issue surat sakit, sehat, kontrol, atau bebas narkoba.', 'from-rose-500/15 to-rose-500/5 text-rose-700 dark:text-rose-300'),
                $this->quickAction('Queue Desk', route('queues'), 'Pantau panggilan pasien dan posisi antrian sebelum konsultasi.', 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-300'),
            ]),
            'mainPanel' => [
                'title' => 'Daftar Pasien Hari Ini',
                'description' => $doctor
                    ? 'Urutan visit yang paling relevan untuk dokter pada hari ini.'
                    : 'Profil dokter belum terhubung ke user ini, jadi daftar pasien belum bisa dipetakan otomatis.',
                'emptyMessage' => 'Belum ada pasien yang terhubung ke dokter ini untuk hari ini.',
                'items' => $patientList->map(fn (VisitRegistration $visit) => $this->listItem(
                    $visit->patient?->full_name ?? 'Pasien',
                    collect([
                        $visit->section?->name,
                        $visit->slot_start_time ? substr((string) $visit->slot_start_time, 0, 5) : 'Walk-in',
                        $visit->doctorSchedule?->room_label,
                    ])->filter()->implode(' | '),
                    $this->statusPill(Str::headline(str_replace('_', ' ', $visit->care_stage)), $this->statusTone($visit->care_stage)),
                    route('medical-records')
                ))->all(),
            ],
            'attentionPanel' => [
                'title' => 'Clinical Follow-up',
                'description' => 'Dokumen dan order yang biasanya tertinggal saat poli sedang ramai.',
                'items' => [
                    $this->attentionItem('Prescription draft', $this->formatNumber($draftPrescriptions), 'Resep yang belum difinalisasi dari visit yang sedang aktif.', route('prescriptions', ['status' => 'draft']), $draftPrescriptions > 0 ? 'warning' : 'success'),
                    $this->attentionItem('Referral draft', $this->formatNumber($draftReferrals), 'Referral yang sudah disiapkan tapi belum issue.', route('referrals', ['referral_status' => 'draft']), $draftReferrals > 0 ? 'warning' : 'info'),
                    $this->attentionItem('Doctor letter draft', $this->formatNumber($draftLetters), 'Surat dokter draft yang belum issue atau print.', route('doctor-letters', ['status' => 'draft']), $draftLetters > 0 ? 'warning' : 'info'),
                ],
            ],
            'queuePanel' => null,
        ];
    }

    private function nursePayload(?User $user, ?Branch $branch): array
    {
        $today = now()->toDateString();
        $branchId = $branch?->id;

        $waitingVitals = $this->visitsForBranch($branchId)
            ->whereDate('visit_date', $today)
            ->where('care_stage', 'waiting_nurse')
            ->where('vital_status', 'pending')
            ->count();

        $recordedToday = VitalSignRecord::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereDate('recorded_at', $today)
            ->when($user?->id, fn (Builder $query) => $query->where('recorded_by_user_id', $user->id))
            ->count();

        $procedureBacklog = VisitProcedure::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereIn('status', ['ordered', 'in_progress'])
            ->whereHas('procedureMaster', fn (Builder $query) => $query->whereIn('performer_scope', ['nurse_only', 'both']))
            ->count();

        $internalDiagnostics = LaboratoryOrder::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('provider_type', 'internal')
            ->whereIn('status', ['ordered', 'sample_collected', 'processing'])
            ->count();

        $waitingList = $this->visitsForBranch($branchId)
            ->with(['patient:id,full_name', 'section:id,name'])
            ->whereDate('visit_date', $today)
            ->where('care_stage', 'waiting_nurse')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'summaryCards' => [
                $this->summaryCard('Waiting Vitals', $this->formatNumber($waitingVitals), 'Pasien yang masih menunggu intake awal dan pengukuran vital.', 'bg-amber-500'),
                $this->summaryCard('Vitals Saya Hari Ini', $this->formatNumber($recordedToday), 'Jumlah vital sign yang sudah direkam oleh user ini hari ini.', 'bg-sky-500'),
                $this->summaryCard('Procedure Backlog', $this->formatNumber($procedureBacklog), 'Tindakan nurse/both yang belum selesai.', 'bg-brand-500'),
                $this->summaryCard('Diagnostics Internal', $this->formatNumber($internalDiagnostics), 'Order penunjang internal yang masih berjalan.', 'bg-emerald-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Vital Signs', route('vital-signs'), 'Input TD, suhu, nadi, respirasi, BB, TB, dan SpO2.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
                $this->quickAction('Procedures', route('procedures'), 'Lihat tindakan yang bisa dikerjakan oleh perawat.', 'from-brand-500/15 to-brand-500/5 text-brand-700 dark:text-brand-300'),
                $this->quickAction('Diagnostics', route('laboratory'), 'Pantau sample collection dan update status pemeriksaan.', 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'),
                $this->quickAction('Queue Desk', route('queues'), 'Lihat beban antrian section untuk bantu prioritas intake.', 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-300'),
            ]),
            'mainPanel' => [
                'title' => 'Pasien Menunggu Intake',
                'description' => 'Urutan pasien yang paling relevan untuk triase, vital, dan tindakan awal.',
                'emptyMessage' => 'Belum ada pasien yang menunggu intake awal di branch fokus.',
                'items' => $waitingList->map(fn (VisitRegistration $visit) => $this->listItem(
                    $visit->patient?->full_name ?? 'Pasien',
                    collect([
                        $visit->section?->name,
                        'Visit ' . $visit->visit_date?->format('d M'),
                    ])->filter()->implode(' | '),
                    $this->statusPill(Str::headline(str_replace('_', ' ', $visit->vital_status)), $this->statusTone($visit->vital_status)),
                    route('vital-signs')
                ))->all(),
            ],
            'attentionPanel' => [
                'title' => 'Focus Perawat',
                'description' => 'Area yang biasanya paling sering jadi bottleneck sebelum pasien masuk dokter.',
                'items' => [
                    $this->attentionItem('Procedure in progress', $this->formatNumber(VisitProcedure::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->where('status', 'in_progress')->count()), 'Tindakan yang sudah berjalan dan perlu dipastikan selesai.', route('procedures', ['order_status' => 'in_progress']), 'warning'),
                    $this->attentionItem('Notifications unread', $this->formatNumber($user?->unreadNotifications()->count() ?? 0), 'Alert terbaru dari dokter, lab, atau antrian yang perlu ditindak.', route('notifications'), 'info'),
                ],
            ],
            'queuePanel' => $this->queuePanelData($branch),
        ];
    }

    private function pharmacistPayload(?User $user, ?Branch $branch): array
    {
        $branchId = $branch?->id;

        $pendingDispense = PrescriptionItem::query()
            ->when($branchId, fn (Builder $query) => $query->whereHas('prescription', fn (Builder $prescriptionQuery) => $prescriptionQuery->where('branch_id', $branchId)))
            ->whereIn('item_type', ['in_house', 'compound'])
            ->whereIn('status', ['pending', 'partial'])
            ->count();

        $partialDispense = Prescription::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('status', 'partial_dispensed')
            ->count();

        $lowStock = $this->lowStockCount($branchId);
        $expiringSoon = $this->expiringBatchCount($branchId, 30);

        $dispenseQueue = PrescriptionItem::query()
            ->with([
                'prescription:id,visit_registration_id,doctor_id,branch_id',
                'prescription.doctor:id,full_name,title_prefix,title_suffix',
                'prescription.visitRegistration:id,patient_id,patient_branch_record_id',
                'prescription.visitRegistration.patient:id,full_name',
                'prescription.visitRegistration.patientBranchRecord:id,medical_record_no',
                'medicine:id,name',
            ])
            ->when($branchId, fn (Builder $query) => $query->whereHas('prescription', fn (Builder $prescriptionQuery) => $prescriptionQuery->where('branch_id', $branchId)))
            ->whereIn('item_type', ['in_house', 'compound'])
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('status')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        return [
            'summaryCards' => [
                $this->summaryCard('Pending Dispense', $this->formatNumber($pendingDispense), 'Item resep in-house atau racikan yang masih menunggu disiapkan.', 'bg-amber-500'),
                $this->summaryCard('Partial Dispense', $this->formatNumber($partialDispense), 'Resep yang belum tertutup penuh dan perlu tindak lanjut.', 'bg-brand-500'),
                $this->summaryCard('Low Stock', $this->formatNumber($lowStock), 'Policy reorder aktif yang sudah menyentuh ambang reorder point.', 'bg-rose-500'),
                $this->summaryCard('Batch Expiring Soon', $this->formatNumber($expiringSoon), 'Batch aktif yang expire dalam 30 hari ke depan.', 'bg-emerald-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Prescriptions', route('prescriptions'), 'Lihat antrean resep, partial dispense, dan override yang dibutuhkan.', 'from-amber-500/15 to-amber-500/5 text-amber-700 dark:text-amber-300'),
                $this->quickAction('Pharmacy', route('pharmacy'), 'Kelola obat, batch, dan stok aktif per branch.', 'from-emerald-500/15 to-emerald-500/5 text-emerald-700 dark:text-emerald-300'),
                $this->quickAction('Reorder Points', route('reorder-points'), 'Pantau low stock dan rekomendasi pembelian berikutnya.', 'from-rose-500/15 to-rose-500/5 text-rose-700 dark:text-rose-300'),
                $this->quickAction('Purchase Orders', route('purchase-orders'), 'Follow-up PO, approval outcome, dan supplier prioritas.', 'from-violet-500/15 to-violet-500/5 text-violet-700 dark:text-violet-300'),
                $this->quickAction('Expiry Monitoring', route('expiry-monitoring'), 'Quarantine batch bermasalah dan pantau batch mendekati expired.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
            ]),
            'mainPanel' => [
                'title' => 'Dispensing Queue',
                'description' => 'Item resep yang paling mungkin perlu diproses lebih dulu di farmasi.',
                'emptyMessage' => 'Belum ada item resep yang menunggu dispensing di branch fokus.',
                'items' => $dispenseQueue->map(fn (PrescriptionItem $item) => $this->listItem(
                    $item->display_name ?: ($item->medicine?->name ?? 'Medicine'),
                    collect([
                        $item->prescription?->visitRegistration?->patient?->full_name,
                        $item->prescription?->doctor?->displayName(),
                        $item->quantity_prescribed ? rtrim(rtrim(number_format((float) $item->quantity_prescribed, 2, '.', ''), '0'), '.') . ' ' . ($item->dispense_unit ?: '') : null,
                    ])->filter()->implode(' | '),
                    $this->statusPill(Str::headline(str_replace('_', ' ', $item->status)), $this->statusTone($item->status)),
                    route('prescriptions')
                ))->all(),
            ],
            'attentionPanel' => [
                'title' => 'Pharmacy Alerts',
                'description' => 'Titik kontrol yang paling sering butuh keputusan cepat dari petugas farmasi.',
                'items' => [
                    $this->attentionItem('PO menunggu approval', $this->formatNumber(PurchaseOrder::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->where('status', 'submitted')->count()), 'PO yang belum bisa dilanjutkan ke penerimaan barang.', route('purchase-orders'), 'warning'),
                    $this->attentionItem('Batch quarantined', $this->formatNumber(MedicineBatch::query()->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))->whereNotNull('quarantined_at')->count()), 'Batch yang sedang diblok sementara dan butuh follow-up.', route('expiry-monitoring'), 'danger'),
                    $this->attentionItem('Notifikasi unread', $this->formatNumber($user?->unreadNotifications()->count() ?? 0), 'Alert low stock, reorder, atau masalah resep yang baru masuk.', route('notifications'), 'info'),
                ],
            ],
            'queuePanel' => null,
        ];
    }

    private function fallbackPayload(?User $user, ?Branch $branch): array
    {
        $accessibleModuleCount = collect([
            'view patient management',
            'view visit registration',
            'view queue management',
            'view medical record management',
            'view billing management',
            'view pharmacy management',
        ])->filter(fn (string $permission) => $this->canAccess($user, permissions: [$permission]))->count();

        return [
            'summaryCards' => [
                $this->summaryCard('Modul Bisa Diakses', $this->formatNumber($accessibleModuleCount), 'Jumlah area operasional utama yang bisa dibuka dari akun ini.', 'bg-sky-500'),
                $this->summaryCard('Branch Aktif', $this->formatNumber(Branch::query()->where('is_active', true)->count()), 'Cabang aktif yang siap dipakai operasional.', 'bg-amber-500'),
                $this->summaryCard('User Aktif', $this->formatNumber(User::query()->where('is_active', true)->count()), 'Akun internal aktif di lingkungan aplikasi.', 'bg-brand-500'),
                $this->summaryCard('Notifikasi Unread', $this->formatNumber($user?->unreadNotifications()->count() ?? 0), 'Alert yang belum dibaca untuk akun ini.', 'bg-emerald-500'),
            ],
            'quickActions' => $this->filterQuickActions($user, [
                $this->quickAction('Notifications', route('notifications'), 'Buka alert terbaru yang relevan untuk role ini.', 'from-brand-500/15 to-brand-500/5 text-brand-700 dark:text-brand-300'),
                $this->quickAction('Profile Settings', route('settings.profile.edit'), 'Perbarui profil, password, dan preferensi akun.', 'from-sky-500/15 to-sky-500/5 text-sky-700 dark:text-sky-300'),
            ]),
            'mainPanel' => [
                'title' => 'Ringkasan Dashboard',
                'description' => 'Akun ini belum punya template dashboard operasional khusus, jadi halaman tetap menampilkan ringkasan umum.',
                'emptyMessage' => 'Belum ada panel operasional khusus untuk role ini.',
                'items' => [],
            ],
            'attentionPanel' => [
                'title' => 'Arah Selanjutnya',
                'description' => 'Kalau role ini nantinya dipakai operasional, kita bisa tambahkan dashboard khusus seperti modul lain.',
                'items' => [
                    $this->attentionItem('Profil role', config('csi_access.roles.' . ($user?->primaryRoleName() ?? '') . '.label', 'Custom role'), 'Role khusus bisa tetap memakai panel notification dan quick access dasar.', route('notifications'), 'info'),
                ],
            ],
            'queuePanel' => $this->queuePanelData($branch),
        ];
    }

    private function dashboardHeader(string $roleKey, string $roleLabel, ?User $user): array
    {
        return match ($roleKey) {
            'clinic-admin', 'super-admin' => [
                'eyebrow' => 'Clinic Control Center',
                'title' => 'Pantau alur klinik, finance, dan inventory dari satu dashboard.',
                'description' => 'Dashboard admin sekarang berfokus pada pekerjaan harian yang benar-benar perlu keputusan cepat: approval, cashflow, queue, dan alert stok.',
                'theme' => 'from-sky-50 via-white to-emerald-50 dark:from-gray-900 dark:via-gray-900 dark:to-emerald-500/10',
                'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            ],
            'front-office' => [
                'eyebrow' => 'Front Office Desk',
                'title' => 'Kelola arrival pasien, booking, dan alur registrasi tanpa buka banyak menu.',
                'description' => 'Semua yang paling sering dibutuhkan front office dikumpulkan di satu tempat: counter context, registrations hari ini, queue, dan dokumen layanan.',
                'theme' => 'from-amber-50 via-white to-sky-50 dark:from-gray-900 dark:via-gray-900 dark:to-amber-500/10',
                'badge' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
            ],
            'cashier' => [
                'eyebrow' => 'Cashier Control',
                'title' => 'Monitor shift, invoice, dan receivable yang perlu dibereskan hari ini.',
                'description' => 'Dashboard kasir sekarang menempatkan invoice yang perlu tindakan, context shift, dan pembayaran hari ini di area yang langsung terlihat.',
                'theme' => 'from-emerald-50 via-white to-brand-50 dark:from-gray-900 dark:via-gray-900 dark:to-emerald-500/10',
                'badge' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            ],
            'doctor' => [
                'eyebrow' => 'Doctor Workspace',
                'title' => 'Fokus pada pasien hari ini, charting terbuka, dan hasil penunjang yang belum direview.',
                'description' => 'Dashboard dokter dirancang untuk membantu berpindah cepat antara patient queue, SOAP, prescription, diagnostics, dan dokumen klinis.',
                'theme' => 'from-rose-50 via-white to-violet-50 dark:from-gray-900 dark:via-gray-900 dark:to-rose-500/10',
                'badge' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            ],
            'nurse' => [
                'eyebrow' => 'Nurse Station',
                'title' => 'Pantau waiting vitals, tindakan awal, dan beban intake klinik hari ini.',
                'description' => 'Dashboard perawat membantu menjaga laju triase tetap rapi sebelum pasien masuk ke dokter atau tindakan lanjut.',
                'theme' => 'from-sky-50 via-white to-brand-50 dark:from-gray-900 dark:via-gray-900 dark:to-sky-500/10',
                'badge' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300',
            ],
            'pharmacist' => [
                'eyebrow' => 'Pharmacy Desk',
                'title' => 'Prioritaskan dispensing, low stock, dan expiry alert tanpa kehilangan konteks branch.',
                'description' => 'Dashboard farmasi dibuat untuk keputusan cepat: resep yang harus disiapkan, stok kritis, dan PO yang perlu di-follow-up.',
                'theme' => 'from-violet-50 via-white to-emerald-50 dark:from-gray-900 dark:via-gray-900 dark:to-violet-500/10',
                'badge' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300',
            ],
            default => [
                'eyebrow' => 'Clinic Dashboard',
                'title' => 'Selamat datang, ' . ($user?->name ?? 'Staff') . '.',
                'description' => 'Dashboard ini menampilkan ringkasan operasional umum dan bisa diperluas lagi sesuai role yang nantinya dipakai di klinik.',
                'theme' => 'from-brand-50 via-white to-sky-50 dark:from-gray-900 dark:via-gray-900 dark:to-brand-500/10',
                'badge' => 'bg-brand-100 text-brand-700 dark:bg-brand-500/15 dark:text-brand-300',
            ],
        } + ['roleLabel' => $roleLabel];
    }

    private function contextChips(
        string $roleLabel,
        ?Branch $branch,
        $activeCounter,
        ?CashierShift $activeShift,
        int $unreadNotifications,
    ): array {
        return [
            ['label' => 'Role', 'value' => $roleLabel],
            ['label' => 'Branch focus', 'value' => $branch ? trim(($branch->code ? $branch->code . ' | ' : '') . $branch->name) : 'Semua branch / belum dipilih'],
            ['label' => 'Counter', 'value' => $activeCounter ? $activeCounter->code . ' | ' . $activeCounter->name : 'Belum dipilih'],
            ['label' => 'Shift', 'value' => $activeShift?->shift_code ?? 'Belum dibuka'],
            ['label' => 'Notifications', 'value' => $this->formatNumber($unreadNotifications) . ' unread'],
        ];
    }

    private function queuePanelData(?Branch $branch): array
    {
        $queueDate = now()->toDateString();
        $boardPayload = $this->queueBoardService->boardData($branch?->id, $queueDate);

        return [
            'title' => 'Queue Snapshot',
            'description' => $branch
                ? 'Mini board antrian untuk branch fokus yang paling relevan dari dashboard.'
                : 'Pilih counter aktif agar mini board antrian bisa menampilkan branch fokus yang benar.',
            'branchLabel' => $branch ? trim(($branch->code ? $branch->code . ' | ' : '') . $branch->name) : null,
            'liveBoard' => $boardPayload['liveBoard'],
            'sectionSummaries' => $boardPayload['sectionSummaries'],
            'deskRoute' => route('queues'),
            'displayRoute' => route('queues.display'),
            'emptyMessage' => $branch
                ? 'Belum ada antrian aktif untuk branch fokus di tanggal hari ini.'
                : 'Belum ada branch context untuk menampilkan mini board antrian.',
        ];
    }

    private function notificationItems(Collection $notifications): array
    {
        return $notifications
            ->map(function (DatabaseNotification $notification): array {
                return [
                    'title' => (string) data_get($notification->data, 'title', 'System alert'),
                    'message' => (string) data_get($notification->data, 'message', ''),
                    'module' => Str::headline(str_replace('_', ' ', (string) data_get($notification->data, 'module', 'system'))),
                    'isUnread' => $notification->read_at === null,
                    'time' => $notification->created_at?->diffForHumans(),
                    'route' => route('notifications.open', $notification),
                ];
            })
            ->all();
    }

    private function resolveDashboardRole(?User $user): string
    {
        if (! $user) {
            return 'general';
        }

        $roles = $user->getRoleNames();

        foreach (['super-admin', 'clinic-admin', 'front-office', 'cashier', 'doctor', 'nurse', 'pharmacist'] as $role) {
            if ($roles->contains($role)) {
                return $role;
            }
        }

        return 'general';
    }

    private function resolveDoctorProfile(?User $user): ?Doctor
    {
        if (! $user || ! $user->hasRole('doctor')) {
            return null;
        }

        return Doctor::query()
            ->where('email', $user->email)
            ->where('is_active', true)
            ->first();
    }

    private function resolveBranchContext(string $roleKey, ?Branch $counterBranch, ?Branch $shiftBranch, ?Doctor $doctor): ?Branch
    {
        if ($shiftBranch) {
            return $shiftBranch;
        }

        if ($counterBranch) {
            return $counterBranch;
        }

        if ($roleKey === 'doctor' && $doctor) {
            $branchId = VisitRegistration::query()
                ->where('doctor_id', $doctor->id)
                ->whereDate('visit_date', now()->toDateString())
                ->orderBy('slot_start_time')
                ->value('branch_id')
                ?: $doctor->schedules()->where('is_active', true)->orderBy('branch_id')->value('branch_id');

            if ($branchId) {
                return Branch::query()->find($branchId);
            }
        }

        if ($roleKey === 'nurse') {
            $branchId = VisitRegistration::query()
                ->whereDate('visit_date', now()->toDateString())
                ->where('care_stage', 'waiting_nurse')
                ->orderBy('branch_id')
                ->value('branch_id');

            if ($branchId) {
                return Branch::query()->find($branchId);
            }
        }

        return Branch::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
    }

    private function shouldShowCounterSelector(?User $user): bool
    {
        return $this->canAccess($user, roles: ['super-admin', 'clinic-admin', 'front-office', 'cashier']);
    }

    private function visitsForBranch(?int $branchId): Builder
    {
        return VisitRegistration::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    private function queuesForBranch(?int $branchId): Builder
    {
        return QueueTicket::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    private function invoicesForBranch(?int $branchId): Builder
    {
        return Invoice::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId));
    }

    private function doctorVisits(?int $doctorId): Builder
    {
        return VisitRegistration::query()
            ->when($doctorId, fn (Builder $query) => $query->where('doctor_id', $doctorId), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    private function draftDocumentCount(): int
    {
        return PatientReferral::query()->where('status', 'draft')->count()
            + DoctorLetter::query()->where('status', 'draft')->count();
    }

    private function openInvoiceCount(?int $branchId): int
    {
        return $this->invoicesForBranch($branchId)
            ->whereIn('status', ['unpaid', 'partial_paid'])
            ->count();
    }

    private function expiringBatchCount(?int $branchId, int $withinDays): int
    {
        return MedicineBatch::query()
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->where('quantity_available', '>', 0)
            ->whereNull('quarantined_at')
            ->whereNotNull('expired_at')
            ->whereDate('expired_at', '>=', now()->toDateString())
            ->whereDate('expired_at', '<=', now()->addDays($withinDays)->toDateString())
            ->count();
    }

    private function lowStockCount(?int $branchId): int
    {
        return $this->lowStockPolicies($branchId)->count();
    }

    private function lowStockPolicies(?int $branchId): Collection
    {
        return MedicineReorderPolicy::query()
            ->select('medicine_reorder_policies.*')
            ->selectSub(function ($query): void {
                $query
                    ->from('medicine_batches')
                    ->selectRaw('COALESCE(SUM(quantity_available), 0)')
                    ->whereColumn('medicine_batches.medicine_id', 'medicine_reorder_policies.medicine_id')
                    ->whereColumn('medicine_batches.branch_id', 'medicine_reorder_policies.branch_id')
                    ->where('medicine_batches.is_active', true)
                    ->where('medicine_batches.quantity_available', '>', 0)
                    ->whereNull('medicine_batches.quarantined_at')
                    ->where(function ($batchQuery): void {
                        $batchQuery
                            ->whereNull('medicine_batches.expired_at')
                            ->orWhereDate('medicine_batches.expired_at', '>=', now()->toDateString());
                    });
            }, 'available_quantity')
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->where('is_active', true)
            ->get()
            ->filter(fn (MedicineReorderPolicy $policy) => (float) $policy->available_quantity <= (float) $policy->reorder_point)
            ->values();
    }

    private function filterQuickActions(?User $user, array $actions): array
    {
        return collect($actions)
            ->filter(fn (array $action) => $this->canAccess($user, roles: $action['roles'] ?? [], permissions: $action['permissions'] ?? []))
            ->values()
            ->all();
    }

    private function canAccess(?User $user, array $roles = [], array $permissions = []): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('super-admin')) {
            return true;
        }

        if ($roles !== [] && $user->hasAnyRole($roles)) {
            return true;
        }

        foreach ($permissions as $permission) {
            try {
                if ($user->can($permission)) {
                    return true;
                }
            } catch (PermissionDoesNotExist) {
                continue;
            }
        }

        return $roles === [] && $permissions === [];
    }

    private function summaryCard(string $label, string $value, string $caption, string $accent): array
    {
        return compact('label', 'value', 'caption', 'accent');
    }

    private function quickAction(string $title, string $route, string $description, string $accent): array
    {
        return [
            'title' => $title,
            'route' => $route,
            'description' => $description,
            'accent' => $accent,
            'roles' => [],
            'permissions' => [],
        ];
    }

    private function listItem(string $title, string $meta, array $status, string $route): array
    {
        return [
            'title' => $title,
            'meta' => $meta,
            'status' => $status,
            'route' => $route,
        ];
    }

    private function attentionItem(string $title, string $value, string $description, string $route, string $tone = 'info'): array
    {
        return [
            'title' => $title,
            'value' => $value,
            'description' => $description,
            'route' => $route,
            'tone' => $tone,
        ];
    }

    private function statusPill(string $label, string $tone): array
    {
        return [
            'label' => $label,
            'tone' => $tone,
        ];
    }

    private function statusTone(string $status): string
    {
        return match ($status) {
            'waiting', 'waiting_nurse', 'waiting_doctor', 'booked', 'submitted', 'draft', 'partial', 'partial_paid', 'reopen_requested', 'resulted' => 'warning',
            'called', 'in_service', 'processing', 'sample_collected', 'sent_to_partner', 'ordered', 'queued' => 'info',
            'completed', 'paid', 'reviewed', 'dispensed', 'issued', 'final', 'ready_for_checkout' => 'success',
            'cancelled', 'voided', 'expired' => 'danger',
            default => 'neutral',
        };
    }

    private function metaLine(int $value, string $unitLabel, string $suffix): string
    {
        return sprintf('%s %s | %s', $this->formatNumber($value), $unitLabel, $suffix);
    }

    private function formatCurrency(float $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }

    private function formatNumber(int|float $value): string
    {
        return number_format((float) $value, fmod((float) $value, 1.0) === 0.0 ? 0 : 2, ',', '.');
    }

    private function contextValue(?string $value, string $fallback): string
    {
        return filled($value) ? $value : $fallback;
    }
}
