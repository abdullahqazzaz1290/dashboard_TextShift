<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LicenseHandshakeController;
use App\Http\Controllers\LicenseDeliveryController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\PaymentLinkController;
use App\Http\Controllers\PaymentMethodController;
use App\Http\Controllers\PublicPaymentLinkController;
use App\Http\Controllers\ReportController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::post('license-handshake', [LicenseHandshakeController::class, 'store'])
    ->withoutMiddleware([ValidateCsrfToken::class])
    ->middleware('throttle:30,1')
    ->name('licenses.handshake');

Route::resource('customers', CustomerController::class)->only([
    'index', 'store', 'edit', 'update',
]);

Route::resource('payment-methods', PaymentMethodController::class)->only([
    'index', 'store', 'edit', 'update',
]);

Route::resource('licenses', LicenseController::class)->only([
    'index', 'create', 'store', 'show', 'edit', 'update',
]);
Route::post('licenses/{license}/delivery/rebuild', [LicenseDeliveryController::class, 'rebuild'])->name('licenses.delivery.rebuild');
Route::get('licenses/{license}/delivery/download', [LicenseDeliveryController::class, 'download'])->name('licenses.delivery.download');
Route::get('deliveries/{license}/{copyNumber}/{token}', [LicenseDeliveryController::class, 'publicCopyDownload'])->name('licenses.delivery.public-copy');

Route::post('licenses/{license}/payment-links', [PaymentLinkController::class, 'store'])->name('payment-links.store');
Route::put('payment-links/{paymentLink}', [PaymentLinkController::class, 'update'])->name('payment-links.update');
Route::post('payment-links/{paymentLink}/mark-paid', [PaymentLinkController::class, 'markPaid'])->name('payment-links.mark-paid');
Route::post('payment-links/{paymentLink}/mark-pending', [PaymentLinkController::class, 'markPending'])->name('payment-links.mark-pending');

Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('pay/{slug}', [PublicPaymentLinkController::class, 'show'])->name('payment-links.public');
