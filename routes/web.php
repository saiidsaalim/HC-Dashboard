<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemotionBatchController;
use App\Http\Controllers\DemotionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MovementApprovalSettingsController;
use App\Http\Controllers\MutationBatchController;
use App\Http\Controllers\MutationController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\DirectorateController;
use App\Http\Controllers\Organization\OrganizationController;
use App\Http\Controllers\Organization\PositionController;
use App\Http\Controllers\Organization\SectionController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\PerformanceTalent\CriticalPositionController;
use App\Http\Controllers\PerformanceTalent\PerformanceTalentController;
use App\Http\Controllers\PerformanceTalent\SuccessorController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalWorkspaceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionApprovalSettingsController;
use App\Http\Controllers\PromotionBatchController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\Workforce\StaffWlaController;
use App\Http\Controllers\Workforce\WlaActivityController;
use App\Http\Controllers\Workforce\WlaAssessmentController;
use App\Http\Controllers\Workforce\WlaHeadcountAnalysisController;
use App\Http\Controllers\Workforce\WlaParticipantController;
use App\Http\Controllers\Workforce\WorkCalendarController;
use App\Http\Controllers\Workforce\WorkScheduleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', function (): RedirectResponse {
    return to_route(auth()->check() ? 'portal' : 'login');
})->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/portal', PortalController::class)->name('portal');
    Route::get('/workspace/{slug}', PortalWorkspaceController::class)->middleware('can:workspace.view')->name('portal.workspace');

    Route::prefix('workspace/performance-talent-management')
        ->name('performance-talent.')
        ->middleware('can:workspace.performance-talent-management.view')
        ->controller(PerformanceTalentController::class)
        ->group(function (): void {
            Route::get('/dashboard', '__invoke')->name('dashboard');
            Route::get('/critical-positions', [CriticalPositionController::class, 'index'])->name('critical-positions.index');
            Route::post('/critical-positions', [CriticalPositionController::class, 'store'])->name('critical-positions.store');
            Route::put('/critical-positions/{criticalPosition}', [CriticalPositionController::class, 'update'])->name('critical-positions.update');
            Route::delete('/critical-positions/{criticalPosition}', [CriticalPositionController::class, 'destroy'])->name('critical-positions.destroy');
            Route::get('/successors', [SuccessorController::class, 'index'])->name('successors.index');
            Route::post('/successors', [SuccessorController::class, 'store'])->name('successors.store');
            Route::put('/successors/{successor}', [SuccessorController::class, 'update'])->name('successors.update');
            Route::delete('/successors/{successor}', [SuccessorController::class, 'destroy'])->name('successors.destroy');
            Route::get('/talent-pool', '__invoke')->name('talent-pool.index');
            Route::get('/development-plans', '__invoke')->name('development-plans.index');
            Route::get('/reports', '__invoke')->name('reports.index');
            Route::get('/data-pegawai', [EmployeeController::class, 'index'])
                ->middleware('can:employees.view')
                ->name('data-pegawai.index');
        });

    Route::middleware('can:workspace.hc-od-career-management.view')->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');

        Route::get('/mutasi', [MutationController::class, 'index'])->middleware('can:mutations.view')->name('mutasi');
        Route::post('/mutasi', [MutationController::class, 'store'])->middleware('can:mutations.create')->name('mutasi.store');
        Route::get('/mutasi/batch/create', [MutationBatchController::class, 'create'])->middleware('can:mutations.create')->name('mutasi.batches.create');
        Route::post('/mutasi/batch', [MutationBatchController::class, 'store'])->middleware('can:mutations.create')->name('mutasi.batches.store');
        Route::get('/mutasi/batch/{employeeMutationBatch}', [MutationBatchController::class, 'show'])->middleware('can:mutations.view')->name('mutasi.batches.show');
        Route::post('/mutasi/batch/{employeeMutationBatch}/items', [MutationBatchController::class, 'storeItem'])->middleware('can:mutations.create')->name('mutasi.batches.items.store');
        Route::get('/mutasi/batch/{employeeMutationBatch}/export-excel', [MutationBatchController::class, 'export'])->middleware('can:mutations.print')->name('mutasi.batches.export');
        Route::post('/mutasi/batch/{employeeMutationBatch}/submit', [MutationBatchController::class, 'submit'])->middleware('can:mutations.submit')->name('mutasi.batches.submit');
        Route::post('/mutasi/batch/{employeeMutationBatch}/approve-selected', [MutationBatchController::class, 'approveSelected'])->middleware('can:mutations.approve')->name('mutasi.batches.approve-selected');
        Route::get('/mutasi/pengaturan-persetujuan', [MovementApprovalSettingsController::class, 'editMutation'])->middleware('can:mutations.manage-approval-settings')->name('mutasi.approval-settings.edit');
        Route::put('/mutasi/pengaturan-persetujuan', [MovementApprovalSettingsController::class, 'updateMutation'])->middleware('can:mutations.manage-approval-settings')->name('mutasi.approval-settings.update');
        Route::get('/mutasi/{employeeMutation}/print', [MutationController::class, 'print'])->middleware('can:mutations.print')->name('mutasi.print');
        Route::put('/mutasi/{employeeMutation}', [MutationController::class, 'update'])->middleware('can:mutations.update')->name('mutasi.update');
        Route::post('/mutasi/{employeeMutation}/submit', [MutationController::class, 'submit'])->middleware('can:mutations.submit')->name('mutasi.submit');
        Route::post('/mutasi/{employeeMutation}/approve', [MutationController::class, 'approve'])->middleware('can:mutations.approve')->name('mutasi.approve');
        Route::post('/mutasi/{employeeMutation}/send-back', [MutationController::class, 'sendBack'])->middleware('can:mutations.approve')->name('mutasi.send-back');
        Route::delete('/mutasi', [MutationController::class, 'destroyMany'])->middleware('can:mutations.delete')->name('mutasi.destroy-many');
        Route::get('/promosi', [PromotionController::class, 'index'])->middleware('can:promotions.view')->name('promosi');
        Route::post('/promosi', [PromotionController::class, 'store'])->middleware('can:promotions.create')->name('promosi.store');
        Route::get('/promosi/batch/create', [PromotionBatchController::class, 'create'])->middleware('can:promotions.create')->name('promosi.batches.create');
        Route::post('/promosi/batch', [PromotionBatchController::class, 'store'])->middleware('can:promotions.create')->name('promosi.batches.store');
        Route::get('/promosi/batch/{employeePromotionBatch}', [PromotionBatchController::class, 'show'])->middleware('can:promotions.view')->name('promosi.batches.show');
        Route::post('/promosi/batch/{employeePromotionBatch}/items', [PromotionBatchController::class, 'storeItem'])->middleware('can:promotions.create')->name('promosi.batches.items.store');
        Route::get('/promosi/batch/{employeePromotionBatch}/export-excel', [PromotionBatchController::class, 'export'])->middleware('can:promotions.print')->name('promosi.batches.export');
        Route::post('/promosi/batch/{employeePromotionBatch}/submit', [PromotionBatchController::class, 'submit'])->middleware('can:promotions.submit')->name('promosi.batches.submit');
        Route::post('/promosi/batch/{employeePromotionBatch}/approve-selected', [PromotionBatchController::class, 'approveSelected'])->middleware('can:promotions.approve')->name('promosi.batches.approve-selected');
        Route::get('/promosi/pengaturan-persetujuan', [PromotionApprovalSettingsController::class, 'edit'])->middleware('can:promotions.manage-approval-settings')->name('promosi.approval-settings.edit');
        Route::put('/promosi/pengaturan-persetujuan', [PromotionApprovalSettingsController::class, 'update'])->middleware('can:promotions.manage-approval-settings')->name('promosi.approval-settings.update');
        Route::get('/promosi/{employeePromotion}/print', [PromotionController::class, 'print'])->middleware('can:promotions.print')->name('promosi.print');
        Route::put('/promosi/{employeePromotion}', [PromotionController::class, 'update'])->middleware('can:promotions.update')->name('promosi.update');
        Route::post('/promosi/{employeePromotion}/submit', [PromotionController::class, 'submit'])->middleware('can:promotions.submit')->name('promosi.submit');
        Route::post('/promosi/{employeePromotion}/approve', [PromotionController::class, 'approve'])->middleware('can:promotions.approve')->name('promosi.approve');
        Route::post('/promosi/{employeePromotion}/send-back', [PromotionController::class, 'sendBack'])->middleware('can:promotions.approve')->name('promosi.send-back');
        Route::delete('/promosi', [PromotionController::class, 'destroyMany'])->middleware('can:promotions.delete')->name('promosi.destroy-many');
        Route::view('/laporan', 'pages.laporan')->middleware('can:reports.view')->name('laporan');
        Route::get('/demosi', [DemotionController::class, 'index'])->middleware('can:demotions.view')->name('demosi');
        Route::post('/demosi', [DemotionController::class, 'store'])->middleware('can:demotions.create')->name('demosi.store');
        Route::get('/demosi/batch/create', [DemotionBatchController::class, 'create'])->middleware('can:demotions.create')->name('demosi.batches.create');
        Route::post('/demosi/batch', [DemotionBatchController::class, 'store'])->middleware('can:demotions.create')->name('demosi.batches.store');
        Route::get('/demosi/batch/{employeeDemotionBatch}', [DemotionBatchController::class, 'show'])->middleware('can:demotions.view')->name('demosi.batches.show');
        Route::post('/demosi/batch/{employeeDemotionBatch}/items', [DemotionBatchController::class, 'storeItem'])->middleware('can:demotions.create')->name('demosi.batches.items.store');
        Route::get('/demosi/batch/{employeeDemotionBatch}/export-excel', [DemotionBatchController::class, 'export'])->middleware('can:demotions.print')->name('demosi.batches.export');
        Route::post('/demosi/batch/{employeeDemotionBatch}/submit', [DemotionBatchController::class, 'submit'])->middleware('can:demotions.submit')->name('demosi.batches.submit');
        Route::post('/demosi/batch/{employeeDemotionBatch}/approve-selected', [DemotionBatchController::class, 'approveSelected'])->middleware('can:demotions.approve')->name('demosi.batches.approve-selected');
        Route::get('/demosi/pengaturan-persetujuan', [MovementApprovalSettingsController::class, 'editDemotion'])->middleware('can:demotions.manage-approval-settings')->name('demosi.approval-settings.edit');
        Route::put('/demosi/pengaturan-persetujuan', [MovementApprovalSettingsController::class, 'updateDemotion'])->middleware('can:demotions.manage-approval-settings')->name('demosi.approval-settings.update');
        Route::get('/demosi/{employeeDemotion}/print', [DemotionController::class, 'print'])->middleware('can:demotions.print')->name('demosi.print');
        Route::put('/demosi/{employeeDemotion}', [DemotionController::class, 'update'])->middleware('can:demotions.update')->name('demosi.update');
        Route::post('/demosi/{employeeDemotion}/submit', [DemotionController::class, 'submit'])->middleware('can:demotions.submit')->name('demosi.submit');
        Route::post('/demosi/{employeeDemotion}/approve', [DemotionController::class, 'approve'])->middleware('can:demotions.approve')->name('demosi.approve');
        Route::post('/demosi/{employeeDemotion}/send-back', [DemotionController::class, 'sendBack'])->middleware('can:demotions.approve')->name('demosi.send-back');
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
            Route::delete('/data-pegawai/import/review/batches/{batch}', [EmployeeController::class, 'destroyImportBatch'])->name('data-pegawai.import.review.destroy');
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
        Route::get('/wla-saya', [StaffWlaController::class, 'index'])->middleware('can:wla.staff.view')->name('wla.staff.index');
        Route::get('/wla-saya/{participant}', [StaffWlaController::class, 'show'])->middleware('can:wla.staff.view')->name('wla.staff.show');
        Route::post('/wla-saya/{participant}/submit', [StaffWlaController::class, 'submit'])->middleware('can:wla.staff.submit')->name('wla.staff.submit');
        Route::scopeBindings()->middleware('can:wla.staff.update')->prefix('wla-saya/{participant}/activities')->name('wla.staff.activities.')->group(function (): void {
            Route::post('/', [StaffWlaController::class, 'storeActivity'])->name('store');
            Route::put('/{activity}', [StaffWlaController::class, 'updateActivity'])->name('update');
            Route::delete('/{activity}', [StaffWlaController::class, 'destroyActivity'])->name('destroy');
        });
        Route::get('/wla', [WlaAssessmentController::class, 'index'])->middleware('can:wla.view')->name('wla');
        Route::get('/wla/headcount-analysis', [WlaHeadcountAnalysisController::class, 'index'])->middleware('can:wla.view')->name('wla.headcount-analysis');
        Route::get('/wla/create', [WlaAssessmentController::class, 'create'])->middleware('can:wla.create')->name('wla.create');
        Route::post('/wla', [WlaAssessmentController::class, 'store'])->middleware('can:wla.create')->name('wla.store');
        Route::get('/wla/{wla}/edit', [WlaAssessmentController::class, 'edit'])->middleware('can:wla.update')->name('wla.edit');
        Route::get('/wla/{wla}/print', [WlaAssessmentController::class, 'print'])->middleware('can:wla.export')->name('wla.print');
        Route::get('/wla/{wla}/export-excel', [WlaAssessmentController::class, 'exportExcel'])->middleware('can:wla.export')->name('wla.export-excel');
        Route::get('/wla/{wla}', [WlaAssessmentController::class, 'show'])->middleware('can:wla.view')->name('wla.show');
        Route::post('/wla/{wla}/finalize', [WlaAssessmentController::class, 'finalize'])->middleware('can:wla.finalize')->name('wla.finalize');
        Route::put('/wla/{wla}', [WlaAssessmentController::class, 'update'])->middleware('can:wla.update')->name('wla.update');
        Route::delete('/wla/{wla}', [WlaAssessmentController::class, 'destroy'])->middleware('can:wla.delete')->name('wla.destroy');

        Route::scopeBindings()->prefix('wla/{wla}/participants')->name('wla.participants.')->group(function (): void {
            Route::post('/', [WlaParticipantController::class, 'store'])->middleware('can:wla.participants.manage')->name('store');
            Route::get('/{participant}', [WlaParticipantController::class, 'show'])->middleware('can:wla.view')->name('show');
            Route::delete('/{participant}', [WlaParticipantController::class, 'destroy'])->middleware('can:wla.participants.manage')->name('destroy');
            Route::post('/{participant}/return', [WlaParticipantController::class, 'returnSubmission'])->middleware('can:wla.participants.review')->name('return');
            Route::post('/{participant}/approve', [WlaParticipantController::class, 'approve'])->middleware('can:wla.participants.review')->name('approve');
            Route::post('/{participant}/import-approved', [WlaParticipantController::class, 'importApproved'])->middleware('can:wla.participants.import-approved')->name('import-approved');
        });

        Route::scopeBindings()->middleware('can:wla.update')->prefix('wla/{wla}/activities')->name('wla.activities.')->group(function (): void {
            Route::post('/', [WlaActivityController::class, 'store'])->name('store');
            Route::put('/{activity}', [WlaActivityController::class, 'update'])->name('update');
            Route::delete('/{activity}', [WlaActivityController::class, 'destroy'])->name('destroy');
        });

        Route::resource('work-schedules', WorkScheduleController::class)->except('show')
            ->middlewareFor(['index'], 'can:work-schedules.view')
            ->middlewareFor(['create', 'store'], 'can:work-schedules.create')
            ->middlewareFor(['edit', 'update'], 'can:work-schedules.update')
            ->middlewareFor(['destroy'], 'can:work-schedules.delete');
        Route::resource('work-calendars', WorkCalendarController::class)->except('show')
            ->middlewareFor(['index'], 'can:work-calendars.view')
            ->middlewareFor(['create', 'store'], 'can:work-calendars.create')
            ->middlewareFor(['edit', 'update'], 'can:work-calendars.update')
            ->middlewareFor(['destroy'], 'can:work-calendars.delete');

        Route::prefix('organization')->name('organization.')->middleware('can:organization.view')->group(function (): void {
            Route::get('/', OrganizationController::class)->name('index');
            Route::resource('directorates', DirectorateController::class)->except('show');
            Route::resource('departments', DepartmentController::class)->except('show');
            Route::resource('units', UnitController::class)->except('show');
            Route::resource('sections', SectionController::class)->except('show');
            Route::resource('positions', PositionController::class)->except('show');
        });
    });
});

require __DIR__.'/auth.php';
require __DIR__.'/fit-proper.php';

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
