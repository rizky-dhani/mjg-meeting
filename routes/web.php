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

Route::get('/backup-download/{filename}', function (string $filename) {
    $service = app(App\Services\DatabaseBackupService::class);
    $path = $service->path($filename);

    abort_unless($path, 404);

    return response()->download($path);
})
    ->middleware(['auth'])
    ->name('backup.download');
