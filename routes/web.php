<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ActiveCounterController;
use App\Modules\Access\Controllers\RolePermissionController;
use App\Modules\AuditLogs\Controllers\AuditLogController;
use App\Modules\Access\Controllers\UserManagementController;
use App\Modules\Backups\Controllers\BackupController;
use App\Modules\Billing\Controllers\BillingController;
use App\Modules\CashierShifts\Controllers\CashierShiftController;
use App\Modules\ClinicSettings\Controllers\ClinicSettingsController;
use App\Modules\Counters\Controllers\CounterController;
use App\Modules\DoctorLetters\Controllers\DoctorLetterController;
use App\Modules\Doctors\Controllers\DoctorController;
use App\Modules\DoctorSchedules\Controllers\DoctorScheduleController;
use App\Modules\Navigation\Controllers\NavigationController;
use App\Modules\Notifications\Controllers\NotificationCenterController;
use App\Modules\Laboratory\Controllers\LaboratoryController;
use App\Modules\MedicalServices\Controllers\MedicalServiceController;
use App\Modules\Icd10\Controllers\Icd10Controller;
use App\Modules\MedicalRecords\Controllers\MedicalRecordController;
use App\Modules\Patients\Controllers\PatientController;
use App\Modules\Patients\Controllers\RegionLookupController;
use App\Modules\PaymentMethods\Controllers\PaymentMethodController;
use App\Modules\Pharmacy\Controllers\PharmacyController;
use App\Modules\Prescriptions\Controllers\PrescriptionController;
use App\Modules\Procedures\Controllers\ProcedureController;
use App\Modules\ProductCategories\Controllers\ProductCategoryController;
use App\Modules\PurchaseOrders\Controllers\PurchaseOrderController;
use App\Modules\PurchaseReturns\Controllers\PurchaseReturnController;
use App\Modules\Queues\Controllers\QueueController;
use App\Modules\Receivables\Controllers\ReceivableController;
use App\Modules\Referrals\Controllers\ReferralController;
use App\Modules\ReorderPoints\Controllers\ReorderPointController;
use App\Modules\Reports\Controllers\ReportController;
use App\Modules\Sections\Controllers\SectionController;
use App\Modules\Suppliers\Controllers\SupplierController;
use App\Modules\DrugInteractions\Controllers\DrugInteractionController;
use App\Modules\GoodsReceipts\Controllers\GoodsReceiptController;
use App\Modules\StockAdjustments\Controllers\StockAdjustmentController;
use App\Modules\StockOpnames\Controllers\StockOpnameController;
use App\Modules\ExpiryMonitoring\Controllers\ExpiryMonitoringController;
use App\Modules\VitalSigns\Controllers\VitalSignController;
use App\Modules\VisitRegistrations\Controllers\VisitRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/active-counter', [ActiveCounterController::class, 'update'])
        ->name('active-counter.update');
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications');
    Route::post('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications/{notification}/open', [NotificationCenterController::class, 'open'])->name('notifications.open');

    Route::get('/users', [UserManagementController::class, 'index'])
        ->name('users');
    Route::post('/users', [UserManagementController::class, 'store'])
        ->name('users.store');
    Route::match(['post', 'put'], '/users/{user}', [UserManagementController::class, 'update'])
        ->name('users.update');
    Route::post('/users/{user}/archive', [UserManagementController::class, 'archive'])
        ->name('users.archive');

    Route::get('/roles', [RolePermissionController::class, 'index'])
        ->name('roles');
    Route::post('/roles', [RolePermissionController::class, 'store'])
        ->name('roles.store');
    Route::match(['post', 'put'], '/roles/{role}', [RolePermissionController::class, 'update'])
        ->name('roles.update');
    Route::post('/roles/{role}/delete', [RolePermissionController::class, 'destroy'])
        ->name('roles.delete');

    Route::get('/clinic', [ClinicSettingsController::class, 'index'])
        ->name('clinic');
    Route::match(['post', 'put'], '/clinic/profile', [ClinicSettingsController::class, 'updateProfile'])
        ->name('clinic.profile');
    Route::post('/clinic/branches', [ClinicSettingsController::class, 'storeBranch'])
        ->name('clinic-branches.store');
    Route::match(['post', 'put'], '/clinic/branches/{branch}', [ClinicSettingsController::class, 'updateBranch'])
        ->name('clinic-branches.update');
    Route::post('/clinic/branches/{branch}/delete', [ClinicSettingsController::class, 'destroyBranch'])
        ->name('clinic-branches.delete');

    Route::get('/counters', [CounterController::class, 'index'])
        ->name('counters');
    Route::post('/counters', [CounterController::class, 'store'])
        ->name('counters.store');
    Route::match(['post', 'put'], '/counters/{counter}', [CounterController::class, 'update'])
        ->name('counters.update');
    Route::post('/counters/{counter}/delete', [CounterController::class, 'destroy'])
        ->name('counters.delete');

    Route::get('/sections', [SectionController::class, 'index'])
        ->name('sections');
    Route::post('/sections', [SectionController::class, 'store'])
        ->name('sections.store');
    Route::match(['post', 'put'], '/sections/{section}', [SectionController::class, 'update'])
        ->name('sections.update');
    Route::post('/sections/{section}/delete', [SectionController::class, 'destroy'])
        ->name('sections.delete');

    Route::get('/doctors', [DoctorController::class, 'index'])
        ->name('doctors');
    Route::post('/doctors', [DoctorController::class, 'store'])
        ->name('doctors.store');
    Route::match(['post', 'put'], '/doctors/{doctor}', [DoctorController::class, 'update'])
        ->name('doctors.update');
    Route::post('/doctors/{doctor}/delete', [DoctorController::class, 'destroy'])
        ->name('doctors.delete');

    Route::get('/doctor-schedules', [DoctorScheduleController::class, 'index'])
        ->name('doctor-schedules');
    Route::post('/doctor-schedules', [DoctorScheduleController::class, 'storeSchedule'])
        ->name('doctor-schedules.store');
    Route::post('/doctor-schedules/leaves', [DoctorScheduleController::class, 'storeLeave'])
        ->name('doctor-leaves.store');
    Route::match(['post', 'put'], '/doctor-schedules/leaves/{leave}', [DoctorScheduleController::class, 'updateLeave'])
        ->name('doctor-leaves.update');
    Route::post('/doctor-schedules/leaves/{leave}/delete', [DoctorScheduleController::class, 'destroyLeave'])
        ->name('doctor-leaves.delete');
    Route::match(['post', 'put'], '/doctor-schedules/{schedule}', [DoctorScheduleController::class, 'updateSchedule'])
        ->name('doctor-schedules.update');
    Route::post('/doctor-schedules/{schedule}/delete', [DoctorScheduleController::class, 'destroySchedule'])
        ->name('doctor-schedules.delete');

    Route::get('/regions/provinces', [RegionLookupController::class, 'provinces'])
        ->name('regions.provinces');
    Route::get('/regions/cities', [RegionLookupController::class, 'cities'])
        ->name('regions.cities');
    Route::get('/regions/districts', [RegionLookupController::class, 'districts'])
        ->name('regions.districts');
    Route::get('/regions/villages', [RegionLookupController::class, 'villages'])
        ->name('regions.villages');

    Route::get('/patients', [PatientController::class, 'index'])
        ->name('patients');
    Route::post('/patients', [PatientController::class, 'store'])
        ->name('patients.store');
    Route::match(['post', 'put'], '/patients/{patient}', [PatientController::class, 'update'])
        ->name('patients.update');
    Route::post('/patients/{patient}/archive', [PatientController::class, 'archive'])
        ->name('patients.archive');

    Route::get('/visit-registrations', [VisitRegistrationController::class, 'index'])
        ->name('visit-registrations');
    Route::get('/visit-registrations/availability', [VisitRegistrationController::class, 'availability'])
        ->name('visit-registrations.availability');
    Route::post('/visit-registrations', [VisitRegistrationController::class, 'store'])
        ->name('visit-registrations.store');
    Route::post('/visit-registrations/{registration}/check-in', [VisitRegistrationController::class, 'checkIn'])
        ->name('visit-registrations.check-in');
    Route::post('/visit-registrations/{registration}/cancel', [VisitRegistrationController::class, 'cancel'])
        ->name('visit-registrations.cancel');
    Route::match(['post', 'put'], '/visit-registrations/{registration}', [VisitRegistrationController::class, 'update'])
        ->name('visit-registrations.update');

    Route::get('/queues', [QueueController::class, 'index'])
        ->name('queues');
    Route::get('/queues/board', [QueueController::class, 'board'])
        ->name('queues.board');
    Route::get('/queues/display', [QueueController::class, 'display'])
        ->name('queues.display');
    Route::post('/queues/call-next', [QueueController::class, 'callNext'])
        ->name('queues.call-next');
    Route::post('/queues/{queue}/action', [QueueController::class, 'action'])
        ->name('queues.action');

    Route::get('/vital-signs', [VitalSignController::class, 'index'])
        ->name('vital-signs');
    Route::post('/vital-signs', [VitalSignController::class, 'store'])
        ->name('vital-signs.store');
    Route::match(['post', 'put'], '/vital-signs/{vitalSign}', [VitalSignController::class, 'update'])
        ->name('vital-signs.update');

    Route::get('/medical-records', [MedicalRecordController::class, 'index'])
        ->name('medical-records');
    Route::post('/medical-records', [MedicalRecordController::class, 'store'])
        ->name('medical-records.store');
    Route::match(['post', 'put'], '/medical-records/{medicalRecord}', [MedicalRecordController::class, 'update'])
        ->name('medical-records.update');
    Route::post('/medical-records/{medicalRecord}/request-reopen', [MedicalRecordController::class, 'requestReopen'])
        ->name('medical-records.request-reopen');
    Route::post('/medical-records/{medicalRecord}/approve-reopen', [MedicalRecordController::class, 'approveReopen'])
        ->name('medical-records.approve-reopen');

    Route::get('/pharmacy', [PharmacyController::class, 'index'])
        ->name('pharmacy');
    Route::post('/pharmacy/medicines', [PharmacyController::class, 'storeMedicine'])
        ->name('pharmacy.store');
    Route::match(['post', 'put'], '/pharmacy/medicines/{medicine}', [PharmacyController::class, 'updateMedicine'])
        ->name('pharmacy.update');
    Route::post('/pharmacy/medicines/{medicine}/delete', [PharmacyController::class, 'destroyMedicine'])
        ->name('pharmacy.delete');
    Route::post('/pharmacy/batches', [PharmacyController::class, 'storeBatch'])
        ->name('medicine-batches.store');
    Route::match(['post', 'put'], '/pharmacy/batches/{batch}', [PharmacyController::class, 'updateBatch'])
        ->name('medicine-batches.update');
    Route::post('/pharmacy/batches/{batch}/delete', [PharmacyController::class, 'destroyBatch'])
        ->name('medicine-batches.delete');

    Route::get('/reorder-points', [ReorderPointController::class, 'index'])
        ->name('reorder-points');
    Route::post('/reorder-points', [ReorderPointController::class, 'store'])
        ->name('reorder-points.store');
    Route::match(['post', 'put'], '/reorder-points/{policy}', [ReorderPointController::class, 'update'])
        ->name('reorder-points.update');
    Route::post('/reorder-points/{policy}/delete', [ReorderPointController::class, 'destroy'])
        ->name('reorder-points.delete');

    Route::get('/drug-interactions', [DrugInteractionController::class, 'index'])
        ->name('drug-interactions');
    Route::post('/drug-interactions', [DrugInteractionController::class, 'store'])
        ->name('drug-interactions.store');
    Route::match(['post', 'put'], '/drug-interactions/{rule}', [DrugInteractionController::class, 'update'])
        ->name('drug-interactions.update');
    Route::post('/drug-interactions/{rule}/delete', [DrugInteractionController::class, 'destroy'])
        ->name('drug-interactions.delete');

    Route::get('/prescriptions', [PrescriptionController::class, 'index'])
        ->name('prescriptions');
    Route::post('/prescriptions', [PrescriptionController::class, 'store'])
        ->name('prescriptions.store');
    Route::match(['post', 'put'], '/prescriptions/{prescription}', [PrescriptionController::class, 'update'])
        ->name('prescriptions.update');
    Route::post('/prescriptions/{prescription}/finalize', [PrescriptionController::class, 'finalize'])
        ->name('prescriptions.finalize');
    Route::post('/prescription-items', [PrescriptionController::class, 'storeItem'])
        ->name('prescription-items.store');
    Route::match(['post', 'put'], '/prescription-items/{item}', [PrescriptionController::class, 'updateItem'])
        ->name('prescription-items.update');
    Route::post('/prescription-items/{item}/delete', [PrescriptionController::class, 'destroyItem'])
        ->name('prescription-items.delete');
    Route::post('/prescription-items/{item}/dispense', [PrescriptionController::class, 'dispense'])
        ->name('prescription-items.dispense');
    Route::post('/prescription-items/{item}/close-remaining', [PrescriptionController::class, 'closeRemaining'])
        ->name('prescription-items.close-remaining');
    Route::post('/prescription-items/{item}/override-interactions', [PrescriptionController::class, 'overrideInteractions'])
        ->name('prescription-items.override-interactions');
    Route::get('/prescription-dispenses/{dispense}/label', [PrescriptionController::class, 'printLabel'])
        ->name('prescription-dispenses.label');

    Route::get('/procedures', [ProcedureController::class, 'index'])
        ->name('procedures');
    Route::post('/procedures/masters', [ProcedureController::class, 'storeMaster'])
        ->name('procedure-masters.store');
    Route::match(['post', 'put'], '/procedures/masters/{master}', [ProcedureController::class, 'updateMaster'])
        ->name('procedure-masters.update');
    Route::post('/procedures/masters/{master}/delete', [ProcedureController::class, 'destroyMaster'])
        ->name('procedure-masters.delete');
    Route::post('/procedures/orders', [ProcedureController::class, 'storeOrder'])
        ->name('procedure-orders.store');
    Route::match(['post', 'put'], '/procedures/orders/{order}', [ProcedureController::class, 'updateOrder'])
        ->name('procedure-orders.update');
    Route::post('/procedures/orders/{order}/delete', [ProcedureController::class, 'destroyOrder'])
        ->name('procedure-orders.delete');

    Route::get('/medical-services', [MedicalServiceController::class, 'index'])
        ->name('medical-services');
    Route::post('/medical-services', [MedicalServiceController::class, 'store'])
        ->name('medical-services.store');
    Route::match(['post', 'put'], '/medical-services/{medicalService}', [MedicalServiceController::class, 'update'])
        ->name('medical-services.update');
    Route::post('/medical-services/{medicalService}/delete', [MedicalServiceController::class, 'destroy'])
        ->name('medical-services.delete');
    Route::post('/medical-service-orders', [MedicalServiceController::class, 'storeOrder'])
        ->name('medical-service-orders.store');
    Route::match(['post', 'put'], '/medical-service-orders/{order}', [MedicalServiceController::class, 'updateOrder'])
        ->name('medical-service-orders.update');
    Route::post('/medical-service-orders/{order}/delete', [MedicalServiceController::class, 'destroyOrder'])
        ->name('medical-service-orders.delete');

    Route::get('/laboratory', [LaboratoryController::class, 'index'])
        ->name('laboratory');
    Route::post('/laboratory/tests', [LaboratoryController::class, 'storeTest'])
        ->name('laboratory-tests.store');
    Route::match(['post', 'put'], '/laboratory/tests/{test}', [LaboratoryController::class, 'updateTest'])
        ->name('laboratory-tests.update');
    Route::post('/laboratory/tests/{test}/delete', [LaboratoryController::class, 'destroyTest'])
        ->name('laboratory-tests.delete');
    Route::post('/laboratory/orders', [LaboratoryController::class, 'storeOrder'])
        ->name('laboratory-orders.store');
    Route::match(['post', 'put'], '/laboratory/orders/{order}', [LaboratoryController::class, 'updateOrder'])
        ->name('laboratory-orders.update');
    Route::post('/laboratory/orders/{order}/delete', [LaboratoryController::class, 'destroyOrder'])
        ->name('laboratory-orders.delete');
    Route::get('/laboratory/orders/{order}/request-print', [LaboratoryController::class, 'requestPrint'])
        ->name('laboratory-orders.request-print');
    Route::get('/laboratory/orders/{order}/result-print', [LaboratoryController::class, 'resultPrint'])
        ->name('laboratory-orders.result-print');

    Route::get('/billing', [BillingController::class, 'index'])
        ->name('billing');
    Route::post('/billing/{invoice}/refresh', [BillingController::class, 'refresh'])
        ->name('billing.refresh');
    Route::post('/billing/{invoice}/pay', [BillingController::class, 'pay'])
        ->name('billing.pay');
    Route::post('/billing/{invoice}/tempo', [BillingController::class, 'tempo'])
        ->name('billing.tempo');
    Route::post('/billing/{invoice}/void', [BillingController::class, 'void'])
        ->name('billing.void');
    Route::get('/billing/{invoice}/print', [BillingController::class, 'print'])
        ->name('billing.print');
    Route::get('/billing/{invoice}/receipt', [BillingController::class, 'receipt'])
        ->name('billing.receipt');

    Route::get('/receivables', [ReceivableController::class, 'index'])
        ->name('receivables');
    Route::post('/receivables/{receivable}/extend', [ReceivableController::class, 'extend'])
        ->name('receivables.extend');
    Route::post('/receivables/{receivable}/settle', [ReceivableController::class, 'settle'])
        ->name('receivables.settle');
    Route::post('/receivables/{receivable}/cancel', [ReceivableController::class, 'cancel'])
        ->name('receivables.cancel');

    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])
        ->name('payment-methods');
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])
        ->name('payment-methods.store');
    Route::match(['post', 'put'], '/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])
        ->name('payment-methods.update');
    Route::post('/payment-methods/{paymentMethod}/delete', [PaymentMethodController::class, 'destroy'])
        ->name('payment-methods.delete');

    Route::get('/cashier-shifts', [CashierShiftController::class, 'index'])
        ->name('cashier-shifts');
    Route::post('/cashier-shifts', [CashierShiftController::class, 'store'])
        ->name('cashier-shifts.store');
    Route::post('/cashier-shifts/{cashierShift}/close', [CashierShiftController::class, 'close'])
        ->name('cashier-shifts.close');

    Route::get('/product-categories', [ProductCategoryController::class, 'index'])
        ->name('product-categories');
    Route::post('/product-categories', [ProductCategoryController::class, 'store'])
        ->name('product-categories.store');
    Route::match(['post', 'put'], '/product-categories/{productCategory}', [ProductCategoryController::class, 'update'])
        ->name('product-categories.update');
    Route::post('/product-categories/{productCategory}/delete', [ProductCategoryController::class, 'destroy'])
        ->name('product-categories.delete');

    Route::get('/suppliers', [SupplierController::class, 'index'])
        ->name('suppliers');
    Route::post('/suppliers', [SupplierController::class, 'store'])
        ->name('suppliers.store');
    Route::match(['post', 'put'], '/suppliers/{supplier}', [SupplierController::class, 'update'])
        ->name('suppliers.update');
    Route::post('/suppliers/{supplier}/delete', [SupplierController::class, 'destroy'])
        ->name('suppliers.delete');

    Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])
        ->name('purchase-orders');
    Route::post('/purchase-orders', [PurchaseOrderController::class, 'store'])
        ->name('purchase-orders.store');
    Route::match(['post', 'put'], '/purchase-orders/{purchaseOrder}', [PurchaseOrderController::class, 'update'])
        ->name('purchase-orders.update');
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit'])
        ->name('purchase-orders.submit');
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve'])
        ->name('purchase-orders.approve');
    Route::post('/purchase-orders/{purchaseOrder}/reject', [PurchaseOrderController::class, 'reject'])
        ->name('purchase-orders.reject');
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel'])
        ->name('purchase-orders.cancel');
    Route::post('/purchase-orders/{purchaseOrder}/delete', [PurchaseOrderController::class, 'destroy'])
        ->name('purchase-orders.delete');

    Route::get('/goods-receipts', [GoodsReceiptController::class, 'index'])
        ->name('goods-receipts');
    Route::post('/goods-receipts', [GoodsReceiptController::class, 'store'])
        ->name('goods-receipts.store');
    Route::match(['post', 'put'], '/goods-receipts/{goodsReceipt}', [GoodsReceiptController::class, 'update'])
        ->name('goods-receipts.update');
    Route::post('/goods-receipts/{goodsReceipt}/cancel', [GoodsReceiptController::class, 'cancel'])
        ->name('goods-receipts.cancel');

    Route::get('/purchase-returns', [PurchaseReturnController::class, 'index'])
        ->name('purchase-returns');
    Route::post('/purchase-returns', [PurchaseReturnController::class, 'store'])
        ->name('purchase-returns.store');
    Route::match(['post', 'put'], '/purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'update'])
        ->name('purchase-returns.update');
    Route::post('/purchase-returns/{purchaseReturn}/complete', [PurchaseReturnController::class, 'complete'])
        ->name('purchase-returns.complete');
    Route::post('/purchase-returns/{purchaseReturn}/cancel', [PurchaseReturnController::class, 'cancel'])
        ->name('purchase-returns.cancel');
    Route::post('/purchase-returns/{purchaseReturn}/delete', [PurchaseReturnController::class, 'destroy'])
        ->name('purchase-returns.delete');

    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])
        ->name('stock-adjustments');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])
        ->name('stock-adjustments.store');
    Route::match(['post', 'put'], '/stock-adjustments/{stockAdjustment}', [StockAdjustmentController::class, 'update'])
        ->name('stock-adjustments.update');
    Route::post('/stock-adjustments/{stockAdjustment}/apply', [StockAdjustmentController::class, 'apply'])
        ->name('stock-adjustments.apply');
    Route::post('/stock-adjustments/{stockAdjustment}/cancel', [StockAdjustmentController::class, 'cancel'])
        ->name('stock-adjustments.cancel');
    Route::post('/stock-adjustments/{stockAdjustment}/delete', [StockAdjustmentController::class, 'destroy'])
        ->name('stock-adjustments.delete');

    Route::get('/stock-opnames', [StockOpnameController::class, 'index'])
        ->name('stock-opnames');
    Route::post('/stock-opnames', [StockOpnameController::class, 'store'])
        ->name('stock-opnames.store');
    Route::match(['post', 'put'], '/stock-opnames/{stockOpname}', [StockOpnameController::class, 'update'])
        ->name('stock-opnames.update');
    Route::post('/stock-opnames/{stockOpname}/finalize', [StockOpnameController::class, 'finalize'])
        ->name('stock-opnames.finalize');
    Route::post('/stock-opnames/{stockOpname}/cancel', [StockOpnameController::class, 'cancel'])
        ->name('stock-opnames.cancel');
    Route::post('/stock-opnames/{stockOpname}/delete', [StockOpnameController::class, 'destroy'])
        ->name('stock-opnames.delete');

    Route::get('/expiry-monitoring', [ExpiryMonitoringController::class, 'index'])
        ->name('expiry-monitoring');
    Route::match(['post', 'put'], '/expiry-monitoring/{batch}', [ExpiryMonitoringController::class, 'update'])
        ->name('expiry-monitoring.update');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->name('audit-logs');
    Route::get('/reports', [ReportController::class, 'index'])
        ->name('reports');
    Route::get('/reports/export/excel', [ReportController::class, 'exportExcel'])
        ->name('reports.export-excel');
    Route::get('/reports/export/pdf', [ReportController::class, 'exportPdf'])
        ->name('reports.export-pdf');

    Route::get('/backups', [BackupController::class, 'index'])
        ->name('backups');
    Route::post('/backups', [BackupController::class, 'store'])
        ->name('backups.store');
    Route::get('/backups/{backup}/download', [BackupController::class, 'download'])
        ->name('backups.download');
    Route::post('/backups/{backup}/restore', [BackupController::class, 'restore'])
        ->name('backups.restore');
    Route::post('/backups/{backup}/delete', [BackupController::class, 'destroy'])
        ->name('backups.delete');

    Route::get('/referrals', [ReferralController::class, 'index'])
        ->name('referrals');
    Route::post('/referrals/destinations', [ReferralController::class, 'storeDestination'])
        ->name('referral-destinations.store');
    Route::match(['post', 'put'], '/referrals/destinations/{destination}', [ReferralController::class, 'updateDestination'])
        ->name('referral-destinations.update');
    Route::post('/referrals/destinations/{destination}/delete', [ReferralController::class, 'destroyDestination'])
        ->name('referral-destinations.delete');
    Route::post('/referrals', [ReferralController::class, 'storeReferral'])
        ->name('referrals.store');
    Route::match(['post', 'put'], '/referrals/{referral}', [ReferralController::class, 'updateReferral'])
        ->name('referrals.update');
    Route::post('/referrals/{referral}/issue', [ReferralController::class, 'issue'])
        ->name('referrals.issue');
    Route::post('/referrals/{referral}/void', [ReferralController::class, 'void'])
        ->name('referrals.void');
    Route::post('/referrals/{referral}/reissue', [ReferralController::class, 'reissue'])
        ->name('referrals.reissue');
    Route::post('/referrals/{referral}/delete', [ReferralController::class, 'destroyReferral'])
        ->name('referrals.delete');
    Route::get('/referrals/{referral}/print', [ReferralController::class, 'print'])
        ->name('referrals.print');

    Route::get('/doctor-letters', [DoctorLetterController::class, 'index'])
        ->name('doctor-letters');
    Route::post('/doctor-letters', [DoctorLetterController::class, 'store'])
        ->name('doctor-letters.store');
    Route::match(['post', 'put'], '/doctor-letters/{doctorLetter}', [DoctorLetterController::class, 'update'])
        ->name('doctor-letters.update');
    Route::post('/doctor-letters/{doctorLetter}/issue', [DoctorLetterController::class, 'issue'])
        ->name('doctor-letters.issue');
    Route::post('/doctor-letters/{doctorLetter}/void', [DoctorLetterController::class, 'void'])
        ->name('doctor-letters.void');
    Route::post('/doctor-letters/{doctorLetter}/reissue', [DoctorLetterController::class, 'reissue'])
        ->name('doctor-letters.reissue');
    Route::post('/doctor-letters/{doctorLetter}/delete', [DoctorLetterController::class, 'destroy'])
        ->name('doctor-letters.delete');
    Route::get('/doctor-letters/{doctorLetter}/print', [DoctorLetterController::class, 'print'])
        ->name('doctor-letters.print');

    Route::get('/icd10', [Icd10Controller::class, 'index'])
        ->name('icd10');
    Route::post('/icd10', [Icd10Controller::class, 'store'])
        ->name('icd10.store');
    Route::match(['post', 'put'], '/icd10/{icd10}', [Icd10Controller::class, 'update'])
        ->name('icd10.update');
    Route::post('/icd10/{icd10}/delete', [Icd10Controller::class, 'destroy'])
        ->name('icd10.delete');

    Route::get('/menu-categories', [NavigationController::class, 'categories'])
        ->name('menu-categories');
    Route::post('/menu-categories', [NavigationController::class, 'storeCategory'])
        ->name('menu-categories.store');
    Route::match(['post', 'put'], '/menu-categories/{category}', [NavigationController::class, 'updateCategory'])
        ->name('menu-categories.update');
    Route::post('/menu-categories/{category}/delete', [NavigationController::class, 'destroyCategory'])
        ->name('menu-categories.delete');

    Route::get('/menus', [NavigationController::class, 'menus'])
        ->name('menus');
    Route::post('/menus', [NavigationController::class, 'storeMenu'])
        ->name('menu-items.store');
    Route::match(['post', 'put'], '/menus/{menu}', [NavigationController::class, 'updateMenu'])
        ->name('menu-items.update');
    Route::post('/menus/{menu}/delete', [NavigationController::class, 'destroyMenu'])
        ->name('menu-items.delete');
});

require __DIR__ . '/auth.php';
