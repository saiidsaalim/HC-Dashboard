<?php

use App\Http\Controllers\FitProperController as F;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])->withoutMiddleware(EnsurePasswordIsChanged::class)->prefix('fit-proper-link')->group(function (): void {
    Route::get('/{token}', [F::class, 'link'])->name('fit-proper.link');
    Route::post('/{token}', [F::class, 'linkSubmit'])->middleware('throttle:20,1')->name('fit-proper.link.submit');
    Route::get('/{token}/cv/{participant}', [F::class, 'linkCv'])->name('fit-proper.link.cv');
});
Route::middleware('auth')->prefix('fit-proper')->name('fit-proper')->group(function (): void {
    Route::get('/', [F::class, 'index']);
    Route::post('/', [F::class, 'store'])->name('.store');
    Route::put('/settings', [F::class, 'settings'])->name('.settings');
    Route::get('/employees', [F::class, 'employees'])->name('.employees');
    Route::get('/{activity}', [F::class, 'show'])->name('.show');
    Route::put('/{activity}', [F::class, 'update'])->name('.update');
    Route::delete('/{activity}', [F::class, 'destroy'])->name('.destroy');
    Route::post('/{activity}/transition', [F::class, 'transition'])->name('.transition');
    Route::put('/{activity}/pics', [F::class, 'pics'])->name('.pics');
    Route::delete('/{activity}/pics/{user}', [F::class, 'deletePic'])->name('.pics.delete');
    Route::post('/{activity}/examiners', [F::class, 'examiner'])->name('.examiners');
    Route::delete('/{activity}/examiners/{examiner}', [F::class, 'deleteExaminer'])->name('.examiners.delete');
    Route::post('/{activity}/examiners/{examiner}/token', [F::class, 'token'])->name('.token');
    Route::get('/{activity}/participants/create', [F::class, 'participantForm'])->name('.participants.create');
    Route::get('/{activity}/participants/{participant}/edit', [F::class, 'participantForm'])->name('.participants.edit');
    Route::post('/{activity}/participants', [F::class, 'saveParticipant'])->name('.participants.store');
    Route::put('/{activity}/participants/{participant}', [F::class, 'saveParticipant'])->name('.participants.update');
    Route::delete('/{activity}/participants/{participant}', [F::class, 'deleteParticipant'])->name('.participants.delete');
    Route::get('/{activity}/participants/{participant}/cv', [F::class, 'cv'])->name('.cv');
    Route::get('/{activity}/assess/{examiner}', [F::class, 'assess'])->name('.assess');
    Route::post('/{activity}/assess/{examiner}', [F::class, 'submit'])->name('.submit');
    Route::get('/{activity}/print', [F::class, 'report'])->name('.print');
    Route::get('/{activity}/excel', [F::class, 'export'])->name('.excel');
});
