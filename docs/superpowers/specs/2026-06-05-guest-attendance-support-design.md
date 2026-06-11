# Guest Attendance Support Design

**Date:** 2026-06-05
**Status:** Draft

## Overview

Meetings may be attended by external entities (vendor PICs, consultants, clients) who are not staff in the company and do not have user accounts. This feature adds guest attendance recording to the meeting management system.

## Schema Changes

### `attendance` table — new columns (all nullable)

| Column              | Type      | Purpose                                       |
| ------------------- | --------- | --------------------------------------------- |
| `guest_name`        | `varchar` | Name of the external guest                    |
| `guest_from`        | `varchar` | Company/organization they represent           |
| `guest_designation` | `varchar` | Their role/title (e.g., "Vendor PIC")         |

### `attendance` table — existing column changes

- **`user_id`**: Made **nullable** (currently `foreignId('user_id')->constrained()`).
  - When attendance is for a staff member: `user_id` is set, guest fields are null.
  - When attendance is for a guest: `user_id` is null, guest fields are set.
- **Unique constraint**: Current `UNIQUE(booking_id, user_id)` must be handled differently since `user_id` can be null. MySQL treats NULLs as not equal in unique indexes, so multiple guest records with `user_id = NULL` could co-exist for the same booking. We will:
  - Drop the existing unique constraint.
  - Add a **partial unique index** (or equivalent) to enforce one-attendance-per-user-per-booking: `UNIQUE(booking_id, user_id)` — this still works since NULLs are excluded from uniqueness checks in most DB engines.
  - Handle guest duplicate prevention at the application level (same guest_name cannot check in twice for the same booking).

### Migration

A new migration `2026_06_05_000001_add_guest_fields_to_attendance_table.php` will:

```php
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires dropping the FK before altering the column
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Drop old unique constraint (name: attendance_booking_id_user_id_unique)
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropUnique(['booking_id', 'user_id']);
        });

        // Make user_id nullable and add guest fields
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();

            $table->string('guest_name')->nullable()->after('user_id');
            $table->string('guest_from')->nullable()->after('guest_name');
            $table->string('guest_designation')->nullable()->after('guest_from');
        });

        // Re-add FK and unique constraint
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['booking_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['booking_id', 'user_id']);
            $table->dropColumn(['guest_name', 'guest_from', 'guest_designation']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['booking_id', 'user_id']);
        });
    }
};
```

## Model Changes

### `App\Models\Attendance`

```php
protected $fillable = [
    'booking_id',
    'user_id',
    'guest_name',
    'guest_from',
    'guest_designation',
    'checked_in_at',
];

// Accessor to determine attendee type
public function getAttendeeTypeAttribute(): string
{
    return $this->user_id ? 'staff' : 'guest';
}

// Scope for guest-only records
public function scopeGuests($query)
{
    return $query->whereNull('user_id');
}

// Scope for staff-only records
public function scopeStaff($query)
{
    return $query->whereNotNull('user_id');
}
```

The `user()` relationship remains `BelongsTo(User::class)` — it will just return null for guest records.

### `App\Models\Booking`

The existing `attendance()` hasMany relationship remains unchanged. No model changes needed.

## Filament Admin Panel

### AttendanceForm (`Schemas/AttendanceForm.php`)

Add a conditional section:

```
Select User (nullable, searchable)
  OR fill in guest details:
    - Guest Name (text input, required if no user selected)
    - Guest From (text input)
    - Guest Designation (text input)
```

Uses a `Select` for user (nullable) and three `TextInput` fields for guest data. Client-side validation: either user_id OR guest_name must be provided.

### AttendancesTable (`Tables/AttendancesTable.php`)

Replace `user.name` column with a dual display:

```php
TextColumn::make('attendee_name')
    ->label('Attendee')
    ->state(fn (Attendance $record): string =>
        $record->user?->name ?? $record->guest_name
    )
    ->searchable(query: fn ($query, $search) =>
        $query->where('guest_name', 'like', "%{$search}%")
            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
    ),
```

Add columns for guest_from and guest_designation (showing empty state for staff).

### AttendanceRelationManager (Booking Resource)

Update to show:

| Attendee Name | From | Designation | Checked In At |
|---|---|---|---|
| John (staff) | — | — | 05 Jun 2026 10:00 |
| Acme Corp (guest) | Acme Corp | Vendor PIC | 05 Jun 2026 10:30 |

## QR Check-In Flow (Livewire)

### Unauthenticated Access

Currently the `/attendance/{qrToken}` route requires `auth`. To enable guest check-in:

1. **Route change**: Remove `auth` middleware from the attendance check-in route.
2. **Component change**: `AttendanceCheckin` Livewire component detects auth status:
   - **Authenticated user**: Existing flow — user sees their name and confirms check-in.
   - **Unauthenticated user**: Show a guest form with three fields (Name, From, Designation) plus a "Check In as Guest" button.
3. **Guest check-in submission**: Creates attendance record with `user_id = null` and the guest fields populated.
4. **Duplicate prevention**: Check if a guest with the same name already checked into this booking.

### Guest Check-in View (new view state)

When the user is not authenticated and booking is valid:

```
Meeting: {{ $booking->title }}
Room: {{ $booking->room->name }}
Time: {{ $booking->starts_at }} - {{ $booking->ends_at }}

[Enter your details to check in as a guest:]

Name:        [________________]
From:        [________________]    (e.g., Acme Corp)
Designation: [________________]    (e.g., Vendor PIC)

[Check In as Guest]
```

### Update `alreadyCheckedIn` Check

The existing check:
```php
$this->alreadyCheckedIn = $this->booking->attendance()
    ->where('user_id', auth()->id())
    ->exists();
```

For guests, this becomes a check by guest_name:
```php
// For authenticated users: check by user_id
// For guest form: check by guest_name
```

## Testing

### New test cases in `tests/Feature/AttendanceCheckinTest.php`

1. **Unauthenticated guest can check in via QR**
   - Visit the check-in page without auth
   - Fill guest name/from/designation
   - Submit → attendance record created with null user_id

2. **Guest duplicate prevention**
   - Same guest name cannot check in twice for the same booking

3. **Staff can still check in normally** (existing tests pass unchanged)

4. **Admin can create guest attendance via Filament**
   - Create attendance record with guest_name but no user_id
   - Verify it displays correctly in the table

### AttendanceFactory

Update to handle both staff (user_id set) and guest (guest_name set) factories:
```php
return [
    'booking_id' => Booking::factory()->approved(),
    'user_id' => fn (array $attrs) => $attrs['guest_name'] ?? null ? null : User::factory(),
    'guest_name' => null,
    'guest_from' => null,
    'guest_designation' => null,
    'checked_in_at' => fake()->dateTimeBetween('-1 hour', 'now'),
];
```

## File Change Summary

| File | Change |
|---|---|
| `database/migrations/2026_06_05_000001_add_guest_fields_to_attendance_table.php` | **New** — add guest columns, make user_id nullable |
| `app/Models/Attendance.php` | Update fillable, add accessor, add scopes |
| `app/Livewire/AttendanceCheckin.php` | Add guest check-in flow (unauthenticated path) |
| `resources/views/livewire/attendance-checkin.blade.php` | Add guest form state |
| `routes/web.php` | Remove auth middleware from attendance check-in route (or handle in component) |
| `app/Filament/Resources/Attendances/Schemas/AttendanceForm.php` | Add guest fields |
| `app/Filament/Resources/Attendances/Tables/AttendancesTable.php` | Add guest columns |
| `app/Filament/Resources/Bookings/RelationManagers/AttendanceRelationManager.php` | Update to show guest data |
| `database/factories/AttendanceFactory.php` | Support guest records |
| `tests/Feature/AttendanceCheckinTest.php` | Add guest check-in tests |

## Constraints

- When `user_id` is null, at least `guest_name` must be provided
- Guest duplicate prevention: same `guest_name + booking_id` cannot be duplicated
- Staff check-in via QR remains unchanged (authenticated user scans QR)
- Old attendance records with non-null `user_id` remain unaffected
