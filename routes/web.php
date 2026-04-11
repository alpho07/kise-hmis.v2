<?php

use App\Http\Controllers\AssessmentPrintController;
use App\Http\Controllers\IntakeAssessmentReportController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;

Route::get('/', function () {
    return view('welcome');
});

// Filament uses /admin/login — redirect the default Laravel login route there
Route::get('/login', fn() => redirect('/admin/login'))->name('login');

Route::middleware(['auth'])->group(function () {
    Route::get('/assessments/{assessment}/print', [AssessmentPrintController::class, 'print'])
        ->name('assessments.print');

    Route::get('/intake-assessments/{intake}/report', IntakeAssessmentReportController::class)
        ->name('intake-assessments.report');

    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/{payment}/print', [ReceiptController::class, 'print'])->name('print');
        Route::get('/{payment}/pdf', [ReceiptController::class, 'pdf'])->name('pdf');
        Route::post('/{payment}/email', [ReceiptController::class, 'email'])->name('email');
    });
});
