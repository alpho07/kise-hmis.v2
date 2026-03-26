<?php

use App\Http\Controllers\AssessmentPrintController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReceiptController;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/assessments/{assessment}/print', [AssessmentPrintController::class, 'print'])
        ->name('assessments.print');

    Route::prefix('receipts')->name('receipts.')->group(function () {
        Route::get('/{payment}/print', [ReceiptController::class, 'print'])->name('print');
        Route::get('/{payment}/pdf', [ReceiptController::class, 'pdf'])->name('pdf');
        Route::post('/{payment}/email', [ReceiptController::class, 'email'])->name('email');
    });
});
