<?php

use App\Http\Controllers\Api\V1\AdminPortalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
        Route::get('/admin/dashboard', [AdminPortalController::class, 'index']);
        Route::get('/me', [PortalController::class, 'me']);
        Route::get('/technicians', [PortalController::class, 'technicians']);
        Route::get('/evidence/{evidence}', [PortalController::class, 'evidence']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::apiResource('bookings', BookingController::class)->only(['index', 'store', 'show']);
        Route::post('/bookings/{b}/quotation', [WorkflowController::class, 'quote']);
        Route::post('/bookings/{b}/quotation/accept', [WorkflowController::class, 'accept']);
        Route::post('/bookings/{b}/start', [WorkflowController::class, 'start']);
        Route::post('/bookings/{b}/evidence', [WorkflowController::class, 'evidence']);
        Route::post('/bookings/{b}/evidence/submit', [WorkflowController::class, 'submitEvidence']);
        Route::post('/bookings/{b}/rating', [WorkflowController::class, 'rating']);
        Route::post('/bookings/{b}/approve', [WorkflowController::class, 'approve']);
        Route::post('/bookings/{b}/dispute', [WorkflowController::class, 'dispute']);
        Route::post('/disputes/{d}/resolve', [WorkflowController::class, 'resolve']);
    });
});
