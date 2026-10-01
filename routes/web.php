<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemotionController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\MutationController;
use App\Http\Controllers\Organization\DepartmentController;
use App\Http\Controllers\Organization\PositionController;
use App\Http\Controllers\Organization\UnitController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\Workforce\WlaActivityController;
use App\Http\Controllers\Workforce\WlaAssessmentController;
use App\Http\Controllers\Workforce\WorkCalendarController;
use App\Http\Controllers\Workforce\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::view('/fit-proper', 'pages.fit-proper')->name('fit-proper');
    Route::get('/mutasi', [MutationController::class, 'index'])->name('mutasi');
    Route::post('/mutasi', [MutationController::class, 'store'])->name('mutasi.store');
    Route::post('/mutasi/import', [MutationController::class, 'upload'])->name('mutasi.import');
    Route::get('/mutasi/{employeeMutation}/print', [MutationController::class, 'print'])->name('mutasi.print');
    Route::put('/mutasi/{employeeMutation}', [MutationController::class, 'update'])->name('mutasi.update');
    Route::post('/mutasi/{employeeMutation}/approve', [MutationController::class, 'approve'])->name('mutasi.approve');
    Route::delete('/mutasi', [MutationController::class, 'destroyMany'])->name('mutasi.destroy-many');
    Route::get('/promosi', [PromotionController::class, 'index'])->name('promosi');
    Route::post('/promosi', [PromotionController::class, 'store'])->name('promosi.store');
    Route::post('/promosi/import', [PromotionController::class, 'upload'])->name('promosi.import');
    Route::get('/promosi/{employeePromotion}/print', [PromotionController::class, 'print'])->name('promosi.print');
    Route::put('/promosi/{employeePromotion}', [PromotionController::class, 'update'])->name('promosi.update');
    Route::post('/promosi/{employeePromotion}/approve', [PromotionController::class, 'approve'])->name('promosi.approve');
    Route::delete('/promosi', [PromotionController::class, 'destroyMany'])->name('promosi.destroy-many');
    Route::view('/laporan', 'pages.laporan')->name('laporan');
    Route::get('/demosi', [DemotionController::class, 'index'])->name('demosi');
    Route::post('/demosi', [DemotionController::class, 'store'])->name('demosi.store');
    Route::post('/demosi/import', [DemotionController::class, 'upload'])->name('demosi.import');
    Route::get('/demosi/{employeeDemotion}/print', [DemotionController::class, 'print'])->name('demosi.print');
    Route::put('/demosi/{employeeDemotion}', [DemotionController::class, 'update'])->name('demosi.update');
    Route::post('/demosi/{employeeDemotion}/approve', [DemotionController::class, 'approve'])->name('demosi.approve');
    Route::delete('/demosi', [DemotionController::class, 'destroyMany'])->name('demosi.destroy-many');
    Route::view('/formasi', 'pages.formasi')->name('formasi');
    Route::get('/konfigurasi-user', [UserManagementController::class, 'index'])->name('konfigurasi-user');
    Route::post('/konfigurasi-user', [UserManagementController::class, 'store'])->name('konfigurasi-user.store');
    Route::post('/konfigurasi-user/import', [UserManagementController::class, 'import'])->name('konfigurasi-user.import');
    Route::put('/konfigurasi-user/{user}', [UserManagementController::class, 'update'])->name('konfigurasi-user.update');
    Route::patch('/konfigurasi-user/{user}/role', [UserManagementController::class, 'updateRole'])->name('konfigurasi-user.role');
    Route::delete('/konfigurasi-user/{user}', [UserManagementController::class, 'destroy'])->name('konfigurasi-user.destroy');
    Route::view('/definitif', 'pages.definitif')->name('definitif');
    Route::get('/data-pegawai', [EmployeeController::class, 'index'])->name('data-pegawai');
    Route::get('/data-pegawai/print', [EmployeeController::class, 'print'])->name('data-pegawai.print');
    Route::get('/data-pegawai/export/excel', [EmployeeController::class, 'exportExcel'])->name('data-pegawai.export');
    Route::post('/data-pegawai', [EmployeeController::class, 'store'])->name('data-pegawai.store');
    Route::post('/data-pegawai/import', [EmployeeController::class, 'upload'])->name('data-pegawai.import');
    Route::get('/data-pegawai/import/review', [EmployeeController::class, 'importReviewIndex'])->name('data-pegawai.import.review.index');
    Route::get('/data-pegawai/import/review/batches/{batch}', [EmployeeController::class, 'importReviewShow'])->name('data-pegawai.import.review.show');
    Route::get('/data-pegawai/import/batches/{batch}', [EmployeeController::class, 'showImportBatch'])->name('data-pegawai.import-batches.show');
    Route::post('/data-pegawai/import/batches/{batch}/approve-candidates', [EmployeeController::class, 'approveImportBatchCandidates'])->name('data-pegawai.import-batches.approve-candidates');
    Route::post('/data-pegawai/import/batches/{batch}/process', [EmployeeController::class, 'processImportBatch'])->name('data-pegawai.import-batches.process');
    Route::get('/data-pegawai/import/rows/{row}', [EmployeeController::class, 'showImportRow'])->name('data-pegawai.import-rows.show');
    Route::post('/data-pegawai/import/rows/{row}/approve', [EmployeeController::class, 'approveImportRow'])->name('data-pegawai.import-rows.approve');
    Route::put('/data-pegawai/{employee}', [EmployeeController::class, 'update'])->name('data-pegawai.update');
    Route::delete('/data-pegawai/hapus-semua', [EmployeeController::class, 'destroyAll'])->name('data-pegawai.destroy-all');
    Route::delete('/data-pegawai/{employee}', [EmployeeController::class, 'destroy'])->name('data-pegawai.destroy');
    Route::delete('/data-pegawai', [EmployeeController::class, 'destroyMany'])->name('data-pegawai.destroy-many');
    Route::get('/employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->name('employees.show');
    Route::get('/wla', [WlaAssessmentController::class, 'index'])->name('wla');
    Route::get('/wla/create', [WlaAssessmentController::class, 'create'])->name('wla.create');
    Route::post('/wla', [WlaAssessmentController::class, 'store'])->name('wla.store');
    Route::get('/wla/{wla}/edit', [WlaAssessmentController::class, 'edit'])->name('wla.edit');
    Route::get('/wla/{wla}', [WlaAssessmentController::class, 'show'])->name('wla.show');
    Route::put('/wla/{wla}', [WlaAssessmentController::class, 'update'])->name('wla.update');
    Route::delete('/wla/{wla}', [WlaAssessmentController::class, 'destroy'])->name('wla.destroy');

    Route::scopeBindings()->prefix('wla/{wla}/activities')->name('wla.activities.')->group(function (): void {
        Route::post('/', [WlaActivityController::class, 'store'])->name('store');
        Route::put('/{activity}', [WlaActivityController::class, 'update'])->name('update');
        Route::delete('/{activity}', [WlaActivityController::class, 'destroy'])->name('destroy');
    });

    Route::resource('work-schedules', WorkScheduleController::class)->except('show');
    Route::resource('work-calendars', WorkCalendarController::class)->except('show');

    Route::prefix('organization')->name('organization.')->group(function (): void {
        Route::resource('departments', DepartmentController::class)->except('show');
        Route::resource('units', UnitController::class)->except('show');
        Route::resource('positions', PositionController::class)->except('show');
    });
});

require __DIR__.'/auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});
