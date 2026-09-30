<?php

use App\Http\Controllers\Admin\PortalController;
use App\Http\Middleware\EnsureAdminSession;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
Route::view('/portal', 'marketplace')->name('portal');
Route::get('/admin', [PortalController::class, 'show'])->defaults('page', 'overview')->middleware(EnsureAdminSession::class)->name('admin');
Route::view('/technician', 'technician')->name('technician');
Route::view('/login', 'marketplace')->name('login');
Route::view('/register', 'marketplace')->name('register');
Route::view('/book', 'marketplace')->name('booking.create');
Route::view('/technician/login', 'marketplace')->name('technician.login');
Route::view('/technician/register', 'marketplace')->name('technician.register');
Route::view('/job-verification', 'verification-live')
    ->name('job.verification');

require __DIR__.'/admin.php';
