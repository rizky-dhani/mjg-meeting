# Attendance QR Code Storage — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When a booking is fully approved, generate the QR code as a PNG file on disk, serve it via storage URL in `ImageEntry` and email notifications.

**Architecture:** Three file changes — generate & save in `BookingsTable::processApproval()`, simplify `ViewBooking` to use `ImageEntry` with `disk('public')`, update `BookingApproved` notification to use storage URL.

**Tech Stack:** Laravel 13, Filament 5, `milon/barcode` ^13.1

---

### Task 1: Generate & Save QR PNG on Final Approval

**Files:**
- Modify: `app/Filament/Resources/Bookings/Tables/BookingsTable.php:225-240`
- No test for this (Filament table action is UI-level)

- [ ] **Step 1: Add `Storage` import**

Add the import at the top of `app/Filament/Resources/Bookings/Tables/BookingsTable.php`:

```php
use Illuminate\Support\Facades\Storage;
```

Add it after line 20 (`use Illuminate\Support\Str;`).

- [ ] **Step 2: Replace the QR code generation block**

Replace lines 225-232 (the `if ($status === 'approved' && $record->isApproved())` block's QR update section):

**Before:**
```php
        if ($status === 'approved' && $record->isApproved()) {
            $qrToken = (string) Str::uuid();
            $qrCodeUrl = url('/attendance/' . $qrToken);

            $record->update([
                'qr_token' => $qrToken,
                'qr_code' => $qrCodeUrl,
            ]);

            $record->attendance()->create([
```

**After:**
```php
        if ($status === 'approved' && $record->isApproved()) {
            $qrToken = (string) Str::uuid();
            $qrCodeUrl = url('/attendance/' . $qrToken);

            $qrPng = DNS2DFacade::getBarcodePNG($qrCodeUrl, 'QRCODE', 8, 8);
            $qrPath = sprintf('bookings/QR-%s.png', $record->booking_number);
            Storage::disk('public')->put($qrPath, $qrPng);

            $record->update([
                'qr_token' => $qrToken,
                'qr_code' => $qrPath,
            ]);

            $record->attendance()->create([
```

- [ ] **Step 3: Add `DNS2DFacade` import**

Add the import at the top of `BookingsTable.php`:

```php
use Milon\Barcode\Facades\DNS2DFacade;
```

Add it after the `use Illuminate\Support\Str;` line.

- [ ] **Step 4: Verify syntax**

Run: `php -l app/Filament/Resources/Bookings/Tables/BookingsTable.php`
Expected: `No syntax errors detected`

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/Bookings/Tables/BookingsTable.php
git commit -m "feat: generate and save QR PNG to disk on final approval"
```

---

### Task 2: Update ViewBooking to Serve Stored QR

**Files:**
- Modify: `app/Filament/Resources/Bookings/Pages/ViewBooking.php:68-80`

- [ ] **Step 1: Replace QR ImageEntry with disk reference**

Replace the QR code section in the `infolist()` method (lines 68-80):

**Before:**
```php
                Section::make('QR Code')
                    ->visible(fn(Booking $record): bool => $record->isApproved())
                    ->components([
                        ImageEntry::make('qr_code')
                            ->label('Scan to check in')
                            ->size(200)
                            ->url(fn(Booking $record): string =>
                                'data:image/png;base64,' . base64_encode(
                                    \Milon\Barcode\Facades\DNS2DFacade::getBarcodePNG($record->qr_code, 'QRCODE', 8, 8)
                                )
                            )
                            ->extraImgAttributes(['class' => 'mx-auto']),
                    ]),
```

**After:**
```php
                Section::make('QR Code')
                    ->visible(fn(Booking $record): bool => $record->isApproved())
                    ->components([
                        ImageEntry::make('qr_code')
                            ->label('Scan to check in')
                            ->size(200)
                            ->disk('public')
                            ->extraImgAttributes(['class' => 'mx-auto']),
                    ]),
```

- [ ] **Step 2: Verify syntax**

Run: `php -l app/Filament/Resources/Bookings/Pages/ViewBooking.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Resources/Bookings/Pages/ViewBooking.php
git commit -m "feat: serve stored QR PNG via ImageEntry with public disk"
```

---

### Task 3: Update Email Notification to Use Storage URL

**Files:**
- Modify: `app/Notifications/BookingApproved.php`

- [ ] **Step 1: Add `Storage` import and update `toMail()`**

Replace the current `toMail()` method and add the `Storage` import.

Add import after line 8 (`use Illuminate\Notifications\Notification;`):
```php
use Illuminate\Support\Facades\Storage;
```

Replace lines 24-40 (`toMail()` method):

**Before:**
```php
    public function toMail(object $notifiable): MailMessage
    {
        $qrCodePng = DNS2DFacade::getBarcodePNG($this->booking->qr_code, 'QRCODE', 8, 8);

        return (new MailMessage)
            ->subject("Booking Approved: {$this->booking->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your booking for **{$this->booking->title}** has been approved.")
            ->line("**Room:** {$this->booking->room->name}")
            ->line("**Location:** {$this->booking->room->location?->name}")
            ->line("**Date:** {$this->booking->starts_at->format('l, M d, Y')}")
            ->line("**Time:** {$this->booking->starts_at->format('H:i')} - {$this->booking->ends_at->format('H:i')}")
            ->line('Scan the QR code below to check in:')
            ->line('<img src="data:image/png;base64,' . base64_encode($qrCodePng) . '" alt="QR Code" style="width:200px;height:200px;" />')
            ->action('View Booking', url("/dashboard/bookings/{$this->booking->id}"))
            ->line("This QR code is valid until the end of the meeting day ({$this->booking->ends_at->format('M d, Y')}).");
    }
```

**After:**
```php
    public function toMail(object $notifiable): MailMessage
    {
        $qrCodeUrl = Storage::disk('public')->url($this->booking->qr_code);

        return (new MailMessage)
            ->subject("Booking Approved: {$this->booking->title}")
            ->greeting("Hello {$notifiable->name}!")
            ->line("Your booking for **{$this->booking->title}** has been approved.")
            ->line("**Room:** {$this->booking->room->name}")
            ->line("**Location:** {$this->booking->room->location?->name}")
            ->line("**Date:** {$this->booking->starts_at->format('l, M d, Y')}")
            ->line("**Time:** {$this->booking->starts_at->format('H:i')} - {$this->booking->ends_at->format('H:i')}")
            ->line('Scan the QR code below to check in:')
            ->line('<img src="' . $qrCodeUrl . '" alt="QR Code" style="width:200px;height:200px;" />')
            ->action('View Booking', url("/dashboard/bookings/{$this->booking->id}"))
            ->line("This QR code is valid until the end of the meeting day ({$this->booking->ends_at->format('M d, Y')}).");
    }
```

- [ ] **Step 2: Remove unused `DNS2DFacade` import**

Remove line 9:
```php
use Milon\Barcode\Facades\DNS2DFacade;
```

- [ ] **Step 3: Verify syntax**

Run: `php -l app/Notifications/BookingApproved.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Verify all three changed files load without error**

```bash
php artisan tinker --execute="
echo 'BookingsTable: ' . ((new ReflectionClass('App\Filament\Resources\Bookings\Tables\BookingsTable'))->getFileName() ? 'OK' : 'FAIL') . PHP_EOL;
echo 'ViewBooking: ' . ((new ReflectionClass('App\Filament\Resources\Bookings\Pages\ViewBooking'))->getFileName() ? 'OK' : 'FAIL') . PHP_EOL;
echo 'BookingApproved: ' . ((new ReflectionClass('App\Notifications\BookingApproved'))->getFileName() ? 'OK' : 'FAIL') . PHP_EOL;
"
```
Expected: All three show `OK`.

- [ ] **Step 5: Commit**

```bash
git add app/Notifications/BookingApproved.php
git commit -m "feat: use stored QR storage URL in email notification"
```
