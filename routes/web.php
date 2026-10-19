<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemotionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MutationController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\Organization\PositionController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalWorkspaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionApprovalSettingsController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\Workforce\StaffWlaController;
use App\Http\Controllers\Workforce\WlaActivityController;
use App\Http\Controllers\Workforce\WlaAssessmentController;
use App\Http\Controllers\Workforce\WlaHeadcountAnalysisController;
use App\Http\Controllers\Workforce\WlaParticipantController;
use App\Http\Controllers\Workforce\WorkCalendarController;
use App\Http\Controllers\Workforce\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', PortalController::class)->name('portal');

Route::middleware('auth')->group(function () {
    Route::get('/workspace/{slug}', PortalWorkspaceController::class)->middleware('can:workspace.view')->name('portal.workspace');

    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

    Route::get('/mutasi', [MutationController::class, 'index'])->middleware('can:mutations.view')->name('mutasi');
    Route::post('/mutasi', [MutationController::class, 'store'])->middleware('can:mutations.create')->name('mutasi.store');
    Route::post('/mutasi/import', [MutationController::class, 'upload'])->middleware('can:mutations.import')->name('mutasi.import');
    Route::get('/mutasi/{employeeMutation}/print', [MutationController::class, 'print'])->middleware('can:mutations.print')->name('mutasi.print');
    Route::put('/mutasi/{employeeMutation}', [MutationController::class, 'update'])->middleware('can:mutations.update')->name('mutasi.update');
    Route::post('/mutasi/{employeeMutation}/approve', [MutationController::class, 'approve'])->middleware('can:mutations.approve')->name('mutasi.approve');
    Route::delete('/mutasi', [MutationController::class, 'destroyMany'])->middleware('can:mutations.delete')->name('mutasi.destroy-many');
    Route::get('/promosi', [PromotionController::class, 'index'])->name('promosi');
    Route::post('/promosi', [PromotionController::class, 'store'])->name('promosi.store');
    Route::post('/promosi/import', [PromotionController::class, 'upload'])->name('promosi.import');
    Route::get('/promosi/pengaturan-persetujuan', [PromotionApprovalSettingsController::class, 'edit'])->name('promosi.approval-settings.edit');
    Route::put('/promosi/pengaturan-persetujuan', [PromotionApprovalSettingsController::class, 'update'])->name('promosi.approval-settings.update');
    Route::get('/promosi/{employeePromotion}/print', [PromotionController::class, 'print'])->name('promosi.print');
    Route::put('/promosi/{employeePromotion}', [PromotionController::class, 'update'])->name('promosi.update');
    Route::post('/promosi/{employeePromotion}/submit', [PromotionController::class, 'submit'])->name('promosi.submit');
    Route::post('/promosi/{employeePromotion}/approve', [PromotionController::class, 'approve'])->name('promosi.approve');
    Route::post('/promosi/{employeePromotion}/send-back', [PromotionController::class, 'sendBack'])->name('promosi.send-back');
    Route::delete('/promosi', [PromotionController::class, 'destroyMany'])->name('promosi.destroy-many');
    Route::view('/laporan', 'pages.laporan')->middleware('can:reports.view')->name('laporan');
    Route::get('/demosi', [DemotionController::class, 'index'])->middleware('can:demotions.view')->name('demosi');
    Route::post('/demosi', [DemotionController::class, 'store'])->middleware('can:demotions.create')->name('demosi.store');
    Route::post('/demosi/import', [DemotionController::class, 'upload'])->middleware('can:demotions.import')->name('demosi.import');
    Route::get('/demosi/{employeeDemotion}/print', [DemotionController::class, 'print'])->middleware('can:demotions.print')->name('demosi.print');
    Route::put('/demosi/{employeeDemotion}', [DemotionController::class, 'update'])->middleware('can:demotions.update')->name('demosi.update');
    Route::post('/demosi/{employeeDemotion}/approve', [DemotionController::class, 'approve'])->middleware('can:demotions.approve')->name('demosi.approve');
    Route::delete('/demosi', [DemotionController::class, 'destroyMany'])->middleware('can:demotions.delete')->name('demosi.destroy-many');
    Route::view('/formasi', 'pages.formasi')->middleware('can:formation.view')->name('formasi');
    Route::get('/konfigurasi-user', [UserManagementController::class, 'index'])->middleware('can:users.view')->name('konfigurasi-user');
    Route::post('/konfigurasi-user', [UserManagementController::class, 'store'])->middleware('can:users.create')->name('konfigurasi-user.store');
    Route::post('/konfigurasi-user/candidates/approve-all', [UserManagementController::class, 'approveAllCandidates'])->middleware('can:users.approve-candidates')->name('konfigurasi-user.candidates.approve-all');
    Route::post('/konfigurasi-user/candidates/{employee}/approve', [UserManagementController::class, 'approveCandidate'])->middleware('can:users.approve-candidates')->name('konfigurasi-user.candidates.approve');
    Route::put('/konfigurasi-user/hak-akses', [UserManagementController::class, 'updateRolePermissions'])->middleware('can:users.manage-access')->name('konfigurasi-user.access.update');
    Route::put('/konfigurasi-user/{user}', [UserManagementController::class, 'update'])->middleware('can:users.update')->name('konfigurasi-user.update');
    Route::patch('/konfigurasi-user/{user}/role', [UserManagementController::class, 'updateRole'])->middleware('can:users.manage-roles')->name('konfigurasi-user.role');
    Route::delete('/konfigurasi-user/{user}', [UserManagementController::class, 'destroy'])->middleware('can:users.delete')->name('konfigurasi-user.destroy');
    Route::view('/definitif', 'pages.definitif')->middleware('can:definitive.view')->name('definitif');
    Route::get('/data-pegawai', [EmployeeController::class, 'index'])->middleware('can:employees.view')->name('data-pegawai');
    Route::get('/data-pegawai/print', [EmployeeController::class, 'print'])->middleware('can:employees.print')->name('data-pegawai.print');
    Route::get('/data-pegawai/export/excel', [EmployeeController::class, 'exportExcel'])->middleware('can:employees.export')->name('data-pegawai.export');
    Route::get('/data-pegawai/sinkronisasi-organisasi', [EmployeeController::class, 'organizationSyncPreview'])->middleware('can:employees.sync-organization')->name('data-pegawai.organization-sync.preview');
    Route::post('/data-pegawai/sinkronisasi-organisasi', [EmployeeController::class, 'organizationSyncApply'])->middleware('can:employees.sync-organization')->name('data-pegawai.organization-sync.apply');
    Route::post('/data-pegawai', [EmployeeController::class, 'store'])->middleware('can:employees.create')->name('data-pegawai.store');
    Route::post('/data-pegawai/import', [EmployeeController::class, 'upload'])->middleware('can:employees.import')->name('data-pegawai.import');
    Route::middleware('can:employees.import')->group(function (): void {
        Route::get('/data-pegawai/import/review', [EmployeeController::class, 'importReviewIndex'])->name('data-pegawai.import.review.index');
        Route::get('/data-pegawai/import/review/batches/{batch}', [EmployeeController::class, 'importReviewShow'])->name('data-pegawai.import.review.show');
        Route::get('/data-pegawai/import/batches/{batch}', [EmployeeController::class, 'showImportBatch'])->name('data-pegawai.import-batches.show');
        Route::post('/data-pegawai/import/batches/{batch}/approve-candidates', [EmployeeController::class, 'approveImportBatchCandidates'])->name('data-pegawai.import-batches.approve-candidates');
        Route::post('/data-pegawai/import/batches/{batch}/process', [EmployeeController::class, 'processImportBatch'])->name('data-pegawai.import-batches.process');
        Route::get('/data-pegawai/import/rows/{row}', [EmployeeController::class, 'showImportRow'])->name('data-pegawai.import-rows.show');
        Route::post('/data-pegawai/import/rows/{row}/approve', [EmployeeController::class, 'approveImportRow'])->name('data-pegawai.import-rows.approve');
    });
    Route::put('/data-pegawai/{employee}', [EmployeeController::class, 'update'])->middleware('can:employees.update')->name('data-pegawai.update');
    Route::delete('/data-pegawai/hapus-semua', [EmployeeController::class, 'destroyAll'])->middleware('can:employees.delete')->name('data-pegawai.destroy-all');
    Route::delete('/data-pegawai/{employee}', [EmployeeController::class, 'destroy'])->middleware('can:employees.delete')->name('data-pegawai.destroy');
    Route::delete('/data-pegawai', [EmployeeController::class, 'destroyMany'])->middleware('can:employees.delete')->name('data-pegawai.destroy-many');
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('can:employees.view')->name('employees.index');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->middleware('can:employees.view')->name('employees.show');
    Route::get('/wla-saya', [StaffWlaController::class, 'index'])->name('wla.staff.index');
    Route::get('/wla-saya/{participant}', [StaffWlaController::class, 'show'])->name('wla.staff.show');
    Route::post('/wla-saya/{participant}/submit', [StaffWlaController::class, 'submit'])->name('wla.staff.submit');
    Route::scopeBindings()->prefix('wla-saya/{participant}/activities')->name('wla.staff.activities.')->group(function (): void {
        Route::post('/', [StaffWlaController::class, 'storeActivity'])->name('store');
        Route::put('/{activity}', [StaffWlaController::class, 'updateActivity'])->name('update');
        Route::delete('/{activity}', [StaffWlaController::class, 'destroyActivity'])->name('destroy');
    });
    Route::get('/wla', [WlaAssessmentController::class, 'index'])->name('wla');
    Route::get('/wla/headcount-analysis', [WlaHeadcountAnalysisController::class, 'index'])->name('wla.headcount-analysis');
    Route::get('/wla/create', [WlaAssessmentController::class, 'create'])->name('wla.create');
    Route::post('/wla', [WlaAssessmentController::class, 'store'])->name('wla.store');
    Route::get('/wla/{wla}/edit', [WlaAssessmentController::class, 'edit'])->name('wla.edit');
    Route::get('/wla/{wla}/print', [WlaAssessmentController::class, 'print'])->name('wla.print');
    Route::get('/wla/{wla}/export-excel', [WlaAssessmentController::class, 'exportExcel'])->name('wla.export-excel');
    Route::get('/wla/{wla}', [WlaAssessmentController::class, 'show'])->name('wla.show');
    Route::post('/wla/{wla}/finalize', [WlaAssessmentController::class, 'finalize'])->name('wla.finalize');
    Route::put('/wla/{wla}', [WlaAssessmentController::class, 'update'])->name('wla.update');
    Route::delete('/wla/{wla}', [WlaAssessmentController::class, 'destroy'])->name('wla.destroy');

    Route::scopeBindings()->prefix('wla/{wla}/participants')->name('wla.participants.')->group(function (): void {
        Route::post('/', [WlaParticipantController::class, 'store'])->name('store');
        Route::get('/{participant}', [WlaParticipantController::class, 'show'])->name('show');
        Route::delete('/{participant}', [WlaParticipantController::class, 'destroy'])->name('destroy');
        Route::post('/{participant}/return', [WlaParticipantController::class, 'returnSubmission'])->name('return');
        Route::post('/{participant}/approve', [WlaParticipantController::class, 'approve'])->name('approve');
        Route::post('/{participant}/import-approved', [WlaParticipantController::class, 'importApproved'])->name('import-approved');
    });

    Route::scopeBindings()->prefix('wla/{wla}/activities')->name('wla.activities.')->group(function (): void {
        Route::post('/', [WlaActivityController::class, 'store'])->name('store');
        Route::put('/{activity}', [WlaActivityController::class, 'update'])->name('update');
        Route::delete('/{activity}', [WlaActivityController::class, 'destroy'])->name('destroy');
    });

    Route::resource('work-schedules', WorkScheduleController::class)->except('show');
    Route::resource('work-calendars', WorkCalendarController::class)->except('show');

    Route::prefix('organization')->name('organization.')->group(function (): void {
        Route::get('/', OrganizationController::class)->name('index');
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('units', UnitController::class)->except('show');
        Route::resource('positions', PositionController::class)->except('show');
    });
});

require __DIR__.'/auth.php';
require __DIR__.'/fit-proper.php';

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
