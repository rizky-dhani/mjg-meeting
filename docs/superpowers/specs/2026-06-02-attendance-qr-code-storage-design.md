# Attendance QR Code Storage

**Date**: 2026-06-02
**Status**: Approved

## Problem

Currently, the attendance QR code is generated on-the-fly as a base64 data URI on every page load. This is inefficient — the same QR is rendered repeatedly instead of being generated once and served as a static file. The QR image should be generated once upon final approval and persisted to disk.

## Solution

When a booking reaches full approval, generate the QR code PNG, save it to the `public` disk, and store the relative path in the `qr_code` column. Serve it directly via storage URL from `ImageEntry` and email notifications.

## Changes

### 1. `app/Filament/Resources/Bookings/Tables/BookingsTable.php`

In `processApproval()`, inside the `if ($status === 'approved' && $record->isApproved())` block:

- Generate `$qrToken` (UUID) and `$qrCodeUrl` (`url('/attendance/' . $qrToken)`) as before
- Render QR PNG: `DNS2DFacade::getBarcodePNG($qrCodeUrl, 'QRCODE', 8, 8)`
- Build filename: `bookings/QR-{$record->booking_number}.png`
- Write to disk: `Storage::disk('public')->put($filename, $pngContent)`
- Store relative path in `qr_code` column instead of the URL
- Keep existing attendance creation and notification logic

### 2. `app/Filament/Resources/Bookings/Pages/ViewBooking.php`

Replace the inline data URI generation with a direct disk reference:

```php
ImageEntry::make('qr_code')
    ->label('Scan to check in')
    ->size(200)
    ->disk('public')
    ->extraImgAttributes(['class' => 'mx-auto']),
```

The `ImageEntry` resolves the relative path from the `public` disk automatically.

### 3. `app/Notifications/BookingApproved.php`

Replace inline base64 QR embedding with the storage URL:

```php
$qrCodeUrl = Storage::disk('public')->url($this->booking->qr_code);
```

Use this URL in the mail `<img>` tag instead of the data URI approach.

## Dependencies

- `milon/barcode` — already installed, generates QR PNG
- `Storage::disk('public')` — already configured, uses `storage/app/public`

## Edge Cases

- **Overwrite**: `Storage::put()` overwrites existing files — safe for re-approvals
- **Directory**: `Storage::put()` auto-creates the `bookings/` directory
- **No migration**: No schema change needed — `qr_code` column already exists
- **Backward compatibility**: DB rows with old URL format in `qr_code` won't match a disk path, but the QR section is only visible for re-approved bookings
