<?php

use App\Livewire\AttendanceCheckin;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/attendance/{qrToken}', AttendanceCheckin::class)
    ->middleware(['auth'])
    ->name('attendance.checkin');
