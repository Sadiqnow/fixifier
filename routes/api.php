<?php

use App\Http\Controllers\Api\V1\JourneyController;
use App\Http\Controllers\Api\V1\JourneyPaymentController;
use App\Http\Controllers\Api\V1\AdminPortalController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\PortalController;
use App\Http\Controllers\Api\V1\WorkflowController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/payments/paystack/webhook', [JourneyPaymentController::class, 'webhook']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
        Route::get('/technician/dashboard', \App\Http\Controllers\Api\V1\TechnicianDashboardController::class);
        Route::get('/technician/schedule', [\App\Http\Controllers\Api\V1\TechnicianScheduleController::class, 'index']);
        Route::get('/technicians/{technician}/slots', [\App\Http\Controllers\Api\V1\TechnicianScheduleController::class, 'slots']);
        Route::put('/technician/schedule', [\App\Http\Controllers\Api\V1\TechnicianScheduleController::class, 'update']);
        Route::get('/admin/dashboard', [AdminPortalController::class, 'index']);
        Route::put('/technician/profile', [JourneyController::class, 'profile']);
        Route::get('/technician/documents', [JourneyController::class, 'documents']);
        Route::post('/technician/documents', [JourneyController::class, 'submitDocument']);
        Route::get('/documents/{document}', [JourneyController::class, 'document']);
        Route::get('/notifications', [JourneyController::class, 'notifications']);
        Route::post('/notifications/{id}/read', [JourneyController::class, 'readNotification']);
        Route::post('/bookings/{b}/journey/{action}', [JourneyController::class, 'act']);
        Route::post('/bookings/{b}/attachments', [JourneyController::class, 'upload']);
        Route::get('/attachments/{id}', [JourneyController::class, 'attachment']);
        Route::post('/bookings/{b}/payments', [JourneyPaymentController::class, 'initialize']);
        Route::post('/bookings/{b}/payments/reconcile', [JourneyPaymentController::class, 'reconcile']);
        Route::post('/bookings/{b}/settlement', [JourneyPaymentController::class, 'settle']);
        Route::post('/bookings/{b}/settlement/reconcile', [JourneyPaymentController::class, 'settlement']);
        Route::put('/technician/payout-destination', [JourneyPaymentController::class, 'destination']);
        Route::get('/technician/earnings', [JourneyPaymentController::class, 'earnings']);
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
