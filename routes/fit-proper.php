<?php

use App\Http\Controllers\FitProperController as F;
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:60,1'])->withoutMiddleware(EnsurePasswordIsChanged::class)->prefix('fit-proper-link')->group(function (): void {
    Route::get('/{token}', [F::class, 'link'])->name('fit-proper.link');
    Route::post('/{token}', [F::class, 'linkSubmit'])->middleware('throttle:20,1')->name('fit-proper.link.submit');
    Route::get('/{token}/cv/{participant}', [F::class, 'linkCv'])->name('fit-proper.link.cv');
});
Route::middleware(['auth', 'can:workspace.hc-od-career-management.view'])->prefix('fit-proper')->name('fit-proper')->group(function (): void {
    Route::get('/', [F::class, 'index'])->middleware('can:fit-proper.view');
    Route::post('/', [F::class, 'store'])->middleware('can:fit-proper.create')->name('.store');
    Route::put('/settings', [F::class, 'settings'])->middleware('can:fit-proper.settings')->name('.settings');
    Route::get('/employees', [F::class, 'employees'])->middleware('can:fit-proper.update')->name('.employees');
    Route::get('/{activity}', [F::class, 'show'])->middleware('can:fit-proper.view')->name('.show');
    Route::put('/{activity}', [F::class, 'update'])->middleware('can:fit-proper.update')->name('.update');
    Route::delete('/{activity}', [F::class, 'destroy'])->middleware('can:fit-proper.delete')->name('.destroy');
    Route::post('/{activity}/transition', [F::class, 'transition'])->middleware('can:fit-proper.finalize')->name('.transition');
    Route::put('/{activity}/pics', [F::class, 'pics'])->middleware('can:fit-proper.update')->name('.pics');
    Route::delete('/{activity}/pics/{user}', [F::class, 'deletePic'])->middleware('can:fit-proper.update')->name('.pics.delete');
    Route::post('/{activity}/examiners', [F::class, 'examiner'])->middleware('can:fit-proper.update')->name('.examiners');
    Route::delete('/{activity}/examiners/{examiner}', [F::class, 'deleteExaminer'])->middleware('can:fit-proper.update')->name('.examiners.delete');
    Route::post('/{activity}/examiners/{examiner}/token', [F::class, 'token'])->middleware('can:fit-proper.update')->name('.token');
    Route::get('/{activity}/participants/create', [F::class, 'participantForm'])->middleware('can:fit-proper.update')->name('.participants.create');
    Route::get('/{activity}/participants/{participant}/edit', [F::class, 'participantForm'])->middleware('can:fit-proper.update')->name('.participants.edit');
    Route::post('/{activity}/participants', [F::class, 'saveParticipant'])->middleware('can:fit-proper.update')->name('.participants.store');
    Route::put('/{activity}/participants/{participant}', [F::class, 'saveParticipant'])->middleware('can:fit-proper.update')->name('.participants.update');
    Route::delete('/{activity}/participants/{participant}', [F::class, 'deleteParticipant'])->middleware('can:fit-proper.update')->name('.participants.delete');
    Route::get('/{activity}/participants/{participant}/cv', [F::class, 'cv'])->middleware('can:fit-proper.view')->name('.cv');
    Route::get('/{activity}/assess/{examiner}', [F::class, 'assess'])->middleware('can:fit-proper.view')->name('.assess');
    Route::post('/{activity}/assess/{examiner}', [F::class, 'submit'])->middleware('can:fit-proper.view')->name('.submit');
    Route::get('/{activity}/print', [F::class, 'report'])->middleware('can:fit-proper.export')->name('.print');
    Route::get('/{activity}/excel', [F::class, 'export'])->middleware('can:fit-proper.export')->name('.excel');
});
