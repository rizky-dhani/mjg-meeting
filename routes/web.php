<?php

use App\Livewire\AttendanceCheckin;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::get('/attendance/{qrToken}', AttendanceCheckin::class)
    ->middleware(['auth'])
    ->name('attendance.checkin');
