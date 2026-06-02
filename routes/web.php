<?php

use App\Livewire\AttendanceCheckin;
use App\Livewire\ShowBookingQr;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/attendance/{qrToken}', AttendanceCheckin::class)
    ->middleware(['auth'])
    ->name('attendance.checkin');

Route::get('/qr/{qrToken}', ShowBookingQr::class)
    ->name('booking.qr');
