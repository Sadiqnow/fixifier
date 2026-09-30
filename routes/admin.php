<?php

use App\Http\Controllers\Admin\ActionController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\PortalController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\EnsureAdminSession;
use Illuminate\Support\Facades\Route;

Route::get('/admin/login', [AuthController::class, 'create'])->name('admin.login');
Route::post('/admin/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('admin.login.store');
Route::prefix('admin')->name('admin.')->middleware(EnsureAdminSession::class)->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/overview', fn () => redirect()->route('admin'))->name('overview');
    foreach (['setup', 'verification', 'requests', 'jobs', 'evidence', 'disputes', 'ratings', 'escrow', 'payouts', 'audit', 'flow'] as $page) {
        Route::get('/'.$page, [PortalController::class, 'show'])->defaults('page', $page)->name($page);
    }
    Route::get('/earnings/calculation', [PortalController::class, 'calculation'])->name('calculation');
    Route::post('/settings/{section}', [SettingsController::class, 'update'])->whereIn('section', ['setup', 'ranking', 'escrow', 'finance'])->name('settings.update');
    Route::post('/catalog/{kind}', [CatalogController::class, 'store'])->whereIn('kind', ['categories', 'areas'])->name('catalog.store');
    Route::post('/catalog/{kind}/{id}', [CatalogController::class, 'toggle'])->whereIn('kind', ['categories', 'areas'])->whereNumber('id')->name('catalog.toggle');
    Route::post('/technicians/{technician}/decision', [ActionController::class, 'decide'])->name('technicians.decide');
    foreach (['assign', 'close', 'requeue'] as $action) {
        Route::post('/bookings/{booking}/'.$action, [ActionController::class, $action])->name('bookings.'.$action);
    }
    Route::post('/bookings/{booking}/evidence/flag', [ActionController::class, 'flag'])->name('evidence.flag');
    Route::post('/bookings/{booking}/dispute/resolve', [ActionController::class, 'resolve'])->name('disputes.resolve');
    Route::post('/reviews/{review}/moderate', [ActionController::class, 'moderate'])->name('reviews.moderate');
    Route::get('/private/evidence/{evidence}', [ActionController::class, 'evidence'])->name('private.evidence');
    Route::get('/private/documents/{document}', [ActionController::class, 'document'])->name('private.document');
});
