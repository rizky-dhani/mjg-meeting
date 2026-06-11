# Guest Attendance Support Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow non-staff guests (e.g., vendor PICs, external consultants) to be recorded as meeting attendees alongside regular staff.

**Architecture:** Add three nullable guest columns (`guest_name`, `guest_from`, `guest_designation`) to the `attendance` table and make `user_id` nullable. The Attendance model distinguishes staff (`user_id` set) from guests (`guest_name` set). QR check-in supports both authenticated (staff) and unauthenticated (guest form) flows. Filament admin panel supports manual guest entry.

**Tech Stack:** Laravel 11, Livewire 3, Filament 3+, MySQL, Pest

---

### Task 1: Migration — Add Guest Fields to Attendance Table

**Files:**
- Create: `database/migrations/2026_06_05_000001_add_guest_fields_to_attendance_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK before altering the column
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        // Drop old unique constraint
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

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: Output includes `2026_06_05_000001_add_guest_fields_to_attendance_table`

- [ ] **Step 3: Verify schema**

Run: `php artisan db:show --table=attendance`
Expected: Shows `guest_name` varchar(255) nullable, `guest_from` varchar(255) nullable, `guest_designation` varchar(255) nullable, `user_id` bigint unsigned nullable

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_05_000001_add_guest_fields_to_attendance_table.php
git commit -m "feat: add guest fields to attendance table"
```

---

### Task 2: Update the Attendance Model

**Files:**
- Modify: `app/Models/Attendance.php`

- [ ] **Step 1: Update model with guest fields, accessor, and scopes**

Change the `$fillable` array and add new methods:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    /** @use HasFactory<\Database\Factories\AttendanceFactory> */
    use HasFactory;

    protected $table = 'attendance';

    protected $fillable = [
        'booking_id',
        'user_id',
        'guest_name',
        'guest_from',
        'guest_designation',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAttendeeTypeAttribute(): string
    {
        return $this->user_id ? 'staff' : 'guest';
    }

    public function scopeGuests($query)
    {
        return $query->whereNull('user_id');
    }

    public function scopeStaff($query)
    {
        return $query->whereNotNull('user_id');
    }
}
```

- [ ] **Step 2: Verify no broken references**

Run: `php artisan tinker --execute="(new App\Models\Attendance)->getFillable();"`
Expected: Shows all 6 fillable fields including guest_name, guest_from, guest_designation

- [ ] **Step 3: Commit**

```bash
git add app/Models/Attendance.php
git commit -m "feat: update Attendance model with guest fields and scopes"
```

---

### Task 3: Update the Attendance Factory

**Files:**
- Modify: `database/factories/AttendanceFactory.php`

- [ ] **Step 1: Update factory to support guest records**

```php
<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory()->approved(),
            'user_id' => fn (array $attrs) => isset($attrs['guest_name']) ? null : User::factory(),
            'guest_name' => null,
            'guest_from' => null,
            'guest_designation' => null,
            'checked_in_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function guest(): static
    {
        return $this->state(fn (array $attrs) => [
            'user_id' => null,
            'guest_name' => fake()->name(),
            'guest_from' => fake()->company(),
            'guest_designation' => fake()->randomElement(['Vendor PIC', 'Consultant', 'Client Representative', 'External Auditor']),
        ]);
    }
}
```

- [ ] **Step 2: Verify factory works**

Run: `php artisan tinker --execute="App\Models\Attendance::factory()->make();"`
Expected: Returns an attendance model with a user_id and null guest fields

Run: `php artisan tinker --execute="App\Models\Attendance::factory()->guest()->make();"`
Expected: Returns an attendance model with null user_id and filled guest_name/guest_from/guest_designation

- [ ] **Step 3: Commit**

```bash
git add database/factories/AttendanceFactory.php
git commit -m "feat: update AttendanceFactory with guest state"
```

---

### Task 4: Update Routes — Allow Unauthenticated Access to Check-In

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Remove auth middleware from attendance check-in route**

Change from:
```php
Route::get('/attendance/{qrToken}', AttendanceCheckin::class)
    ->middleware(['auth'])
    ->name('attendance.checkin');
```

To:
```php
Route::get('/attendance/{qrToken}', AttendanceCheckin::class)
    ->name('attendance.checkin');
```

- [ ] **Step 2: Verify route is accessible**

Run: `php artisan route:list --path=attendance`
Expected: Shows GET attendance/{qrToken} without auth middleware

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat: allow unauthenticated access to attendance check-in route"
```

---

### Task 5: Update Livewire Component — Add Guest Check-In Flow

**Files:**
- Modify: `app/Livewire/AttendanceCheckin.php`

- [ ] **Step 1: Update the component with guest check-in support**

```php
<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Booking;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class AttendanceCheckin extends Component
{
    #[Locked]
    public string $qrToken;

    #[Locked]
    public bool $isGuest = false;

    public ?Booking $booking = null;

    public bool $alreadyCheckedIn = false;

    public bool $isExpired = false;

    public bool $checkedIn = false;

    public bool $confirming = false;

    public bool $loading = true;

    #[Rule('required|string|max:255')]
    public string $guestName = '';

    #[Rule('nullable|string|max:255')]
    public string $guestFrom = '';

    #[Rule('nullable|string|max:255')]
    public string $guestDesignation = '';

    public function mount(string $qrToken): void
    {
        $this->qrToken = $qrToken;
        $this->isGuest = ! auth()->check();
        $this->loadBooking();
    }

    public function loadBooking(): void
    {
        $this->booking = Booking::query()
            ->where('qr_token', $this->qrToken)
            ->whereHas('approvals', function ($q) {
                $q->where('status', 'approved');
            })
            ->with(['room.location', 'attendance'])
            ->first();

        if (! $this->booking) {
            $this->booking = null;
            $this->loading = false;

            return;
        }

        if ($this->booking->isExpired()) {
            $this->isExpired = true;
            $this->loading = false;

            return;
        }

        if (auth()->check()) {
            $this->alreadyCheckedIn = $this->booking->attendance()
                ->where('user_id', auth()->id())
                ->exists();
        }

        $this->loading = false;
    }

    public function confirmCheckIn(): void
    {
        if ($this->alreadyCheckedIn || $this->isExpired || ! $this->booking) {
            return;
        }

        $this->confirming = true;
    }

    public function cancelCheckIn(): void
    {
        $this->confirming = false;
    }

    public function checkIn(): void
    {
        if (! $this->booking || $this->isExpired) {
            $this->confirming = false;

            return;
        }

        if (auth()->check()) {
            if ($this->alreadyCheckedIn) {
                $this->confirming = false;

                return;
            }

            Attendance::create([
                'booking_id' => $this->booking->id,
                'user_id' => auth()->id(),
                'checked_in_at' => now(),
            ]);
        } else {
            $this->validate();

            $alreadyCheckedIn = $this->booking->attendance()
                ->whereNull('user_id')
                ->where('guest_name', $this->guestName)
                ->exists();

            if ($alreadyCheckedIn) {
                $this->alreadyCheckedIn = true;

                return;
            }

            Attendance::create([
                'booking_id' => $this->booking->id,
                'user_id' => null,
                'guest_name' => $this->guestName,
                'guest_from' => $this->guestFrom,
                'guest_designation' => $this->guestDesignation,
                'checked_in_at' => now(),
            ]);
        }

        $this->checkedIn = true;
        $this->confirming = false;
    }

    public function render()
    {
        return view('livewire.attendance-checkin')
            ->layout('layouts.app');
    }
}
```

- [ ] **Step 2: Verify no PHP errors**

Run: `php artisan livewire:configure --check` or just verify syntax with `php -l app/Livewire/AttendanceCheckin.php`
Expected: No syntax errors

- [ ] **Step 3: Commit**

```bash
git add app/Livewire/AttendanceCheckin.php
git commit -m "feat: add guest check-in flow to Livewire component"
```

---

### Task 6: Update Blade View — Add Guest Check-In Form

**Files:**
- Modify: `resources/views/livewire/attendance-checkin.blade.php`

- [ ] **Step 1: Replace the view with guest-supporting version**

The existing view has sections for: loading, invalid booking, expired, already checked in, success, confirming, and default (show meeting + check-in button). We need to:
- Modify the `$alreadyCheckedIn` section to handle guest display
- Modify the `$checkedIn` section to handle guest display
- Add a new guest form section (before the default section)
- Show the default section only for authenticated users

```blade
<div class="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-lg w-full space-y-8">
        @if ($loading)
            <div class="text-center">
                <svg class="animate-spin h-10 w-10 text-indigo-600 mx-auto" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="mt-3 text-gray-500">Loading meeting details...</p>
            </div>

        @elseif (! $booking)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-red-500 text-5xl mb-4">&#10060;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Invalid QR Code</h2>
                <p class="text-gray-500">This QR code is not valid or the booking has been cancelled.</p>
            </div>

        @elseif ($isExpired)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-yellow-500 text-5xl mb-4">&#9203;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">QR Code Expired</h2>
                <p class="text-gray-500">This QR code expired at the end of the meeting day.</p>
            </div>

        @elseif ($alreadyCheckedIn)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-green-500 text-5xl mb-4">&#10003;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Already Checked In</h2>
                <p class="text-gray-500">
                    @if ($isGuest)
                        <strong>{{ $guestName }}</strong> has already recorded attendance for this meeting.
                    @else
                        You've already recorded your attendance for this meeting.
                    @endif
                </p>
                <div class="mt-6 bg-gray-50 rounded-lg p-4 text-left">
                    <h3 class="font-semibold text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $booking->room->name }} &middot; {{ $booking->room->location?->name }}</p>
                    <p class="text-sm text-gray-500">{{ $booking->starts_at->format('M d, Y H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}</p>
                </div>
            </div>

        @elseif ($checkedIn)
            <div class="bg-white shadow rounded-lg p-8 text-center">
                <div class="text-green-500 text-5xl mb-4">&#10003;</div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">Attendance Recorded!</h2>
                <p class="text-gray-500">
                    @if ($isGuest)
                        Check-in for <strong>{{ $guestName }}</strong> has been recorded successfully.
                    @else
                        Your check-in has been recorded successfully.
                    @endif
                </p>
                <div class="mt-6 bg-gray-50 rounded-lg p-4 text-left">
                    <h3 class="font-semibold text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $booking->room->name }} &middot; {{ $booking->room->location?->name }}</p>
                    <p class="text-sm text-gray-500">{{ $booking->starts_at->format('M d, Y H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}</p>
                </div>
            </div>

        @elseif ($confirming)
            <div class="bg-white shadow rounded-lg p-8">
                <div class="text-center mb-6">
                    <div class="text-amber-500 text-5xl mb-4">&#9888;</div>
                    <h2 class="text-2xl font-bold text-gray-900">Confirm Check-In</h2>
                    <p class="text-gray-500 mt-2">Please confirm your attendance for this meeting.</p>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-lg text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->room->name }}
                        @if($booking->room->location)
                            &middot; {{ $booking->room->location->name }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->starts_at->format('l, M d, Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}
                    </p>
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-500 mb-4">
                        Checking in as <strong>{{ auth()->user()->name }}</strong>
                    </p>
                    <div class="flex gap-3">
                        <button
                            wire:click="cancelCheckIn"
                            class="flex-1 bg-white text-gray-700 py-3 px-6 rounded-lg font-medium border border-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            wire:click="checkIn"
                            class="flex-1 bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                        >
                            Confirm Check-In
                        </button>
                    </div>
                </div>
            </div>

        @elseif ($isGuest)
            {{-- Guest check-in form --}}
            <div class="bg-white shadow rounded-lg p-8">
                <div class="text-center mb-6">
                    <div class="text-indigo-500 text-5xl mb-4">&#128197;</div>
                    <h2 class="text-2xl font-bold text-gray-900">Guest Check-In</h2>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-lg text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->room->name }}
                        @if($booking->room->location)
                            &middot; {{ $booking->room->location->name }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->starts_at->format('l, M d, Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}
                    </p>
                </div>

                <form wire:submit="checkIn" class="space-y-4">
                    <p class="text-sm text-gray-600">Enter your details to check in as a guest:</p>

                    <div>
                        <label for="guestName" class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                        <input
                            wire:model="guestName"
                            id="guestName"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Your full name"
                        >
                        @error('guestName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="guestFrom" class="block text-sm font-medium text-gray-700">From</label>
                        <input
                            wire:model="guestFrom"
                            id="guestFrom"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g., Acme Corp"
                        >
                    </div>

                    <div>
                        <label for="guestDesignation" class="block text-sm font-medium text-gray-700">Designation</label>
                        <input
                            wire:model="guestDesignation"
                            id="guestDesignation"
                            type="text"
                            class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="e.g., Vendor PIC"
                        >
                    </div>

                    <button
                        type="submit"
                        class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                    >
                        Check In as Guest
                    </button>
                </form>
            </div>

        @else
            {{-- Authenticated user check-in --}}
            <div class="bg-white shadow rounded-lg p-8">
                <div class="text-center mb-6">
                    <div class="text-indigo-500 text-5xl mb-4">&#128197;</div>
                    <h2 class="text-2xl font-bold text-gray-900">Meeting Check-In</h2>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <h3 class="font-semibold text-lg text-gray-900">{{ $booking->title }}</h3>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->room->name }}
                        @if($booking->room->location)
                            &middot; {{ $booking->room->location->name }}
                        @endif
                    </p>
                    <p class="text-sm text-gray-500 mt-1">
                        {{ $booking->starts_at->format('l, M d, Y') }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $booking->starts_at->format('H:i') }} &ndash; {{ $booking->ends_at->format('H:i') }}
                    </p>
                    @if($booking->description)
                        <p class="text-sm text-gray-600 mt-2">{{ $booking->description }}</p>
                    @endif
                </div>

                <div class="text-center">
                    <p class="text-sm text-gray-500 mb-4">
                        You're checking in as <strong>{{ auth()->user()->name }}</strong>
                    </p>
                    <button
                        wire:click="confirmCheckIn"
                        class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-medium hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors"
                    >
                        Mark Attendance
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
```

- [ ] **Step 2: Verify the view file is valid Blade**

Run: `php artisan view:cache`
Expected: No errors, views cached successfully

- [ ] **Step 3: Commit**

```bash
git add resources/views/livewire/attendance-checkin.blade.php
git commit -m "feat: add guest check-in form to attendance view"
```

---

### Task 7: Update Filament Attendance Form

**Files:**
- Modify: `app/Filament/Resources/Attendances/Schemas/AttendanceForm.php`

- [ ] **Step 1: Add guest fields to the attendance form**

```php
<?php

namespace App\Filament\Resources\Attendances\Schemas;

use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Attendance')
                    ->schema([
                        Select::make('user_id')
                            ->label('Staff Member')
                            ->placeholder('Select a staff member (or fill guest details below)')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->nullable(),
                        TextInput::make('guest_name')
                            ->label('Guest Name')
                            ->placeholder('Name of the guest (if not a staff member)')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('guest_from')
                            ->label('Guest From')
                            ->placeholder('e.g., Acme Corp')
                            ->maxLength(255)
                            ->nullable(),
                        TextInput::make('guest_designation')
                            ->label('Guest Designation')
                            ->placeholder('e.g., Vendor PIC')
                            ->maxLength(255)
                            ->nullable(),
                    ]),
            ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Resources/Attendances/Schemas/AttendanceForm.php
git commit -m "feat: add guest fields to Filament attendance form"
```

---

### Task 8: Update Filament Attendance Table

**Files:**
- Modify: `app/Filament/Resources/Attendances/Tables/AttendancesTable.php`

- [ ] **Step 1: Add guest columns and update attendee display**

```php
<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('attendee_name')
                    ->label('Attendee')
                    ->searchable(query: fn ($query, $search) =>
                        $query->where('guest_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    )
                    ->sortable(query: fn ($query, $direction) =>
                        $query->orderBy('guest_name', $direction)
                    )
                    ->state(fn (Attendance $record): string =>
                        $record->user?->name ?? $record->guest_name ?? 'N/A'
                    ),
                TextColumn::make('guest_from')
                    ->label('From')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_from
                    )
                    ->sortable(),
                TextColumn::make('guest_designation')
                    ->label('Designation')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_designation
                    )
                    ->sortable(),
                TextColumn::make('attendee_type')
                    ->label('Type')
                    ->state(fn (Attendance $record): string => $record->attendee_type)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'staff' => 'success',
                        'guest' => 'warning',
                    }),
                TextColumn::make('checked_in_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('booking.starts_at')
                    ->dateTime('M d, Y H:i')
                    ->label('Meeting Date')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->defaultSort('checked_in_at', 'desc');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Resources/Attendances/Tables/AttendancesTable.php
git commit -m "feat: add guest columns to Filament attendance table"
```

---

### Task 9: Update Attendance Relation Manager on Booking Resource

**Files:**
- Modify: `app/Filament/Resources/Bookings/RelationManagers/AttendanceRelationManager.php`

- [ ] **Step 1: Update relation manager with guest display**

```php
<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\Attendance;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendance';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('attendee_name')
                    ->label('Attendee')
                    ->state(fn (Attendance $record): string =>
                        $record->user?->name ?? $record->guest_name ?? 'N/A'
                    )
                    ->searchable(query: fn ($query, $search) =>
                        $query->where('guest_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    )
                    ->sortable(),
                TextColumn::make('guest_from')
                    ->label('From')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_from
                    ),
                TextColumn::make('guest_designation')
                    ->label('Designation')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_designation
                    ),
                TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime('d F Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('checked_in_at', 'desc');
    }

    public static function getRecordTitle(): ?string
    {
        return 'Attendance';
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return 'Attendance';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Filament/Resources/Bookings/RelationManagers/AttendanceRelationManager.php
git commit -m "feat: update attendance relation manager with guest display"
```

---

### Task 10: Write and Run Tests

**Files:**
- Modify: `tests/Feature/AttendanceCheckinTest.php`

- [ ] **Step 1: Add guest check-in tests to the existing test file**

Add these test cases after the existing ones (before the closing `);` if any, or at end of file):

```php
it('allows unauthenticated guest to check in via QR', function () {
    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->assertSet('isGuest', true)
        ->assertSee('Guest Check-In')
        ->set('guestName', 'John External')
        ->set('guestFrom', 'Acme Corp')
        ->set('guestDesignation', 'Vendor PIC')
        ->call('checkIn')
        ->assertSet('checkedIn', true);

    $attendance = Attendance::where('booking_id', $this->booking->id)
        ->whereNull('user_id')
        ->where('guest_name', 'John External')
        ->first();

    expect($attendance)->not->toBeNull();
    expect($attendance->guest_from)->toBe('Acme Corp');
    expect($attendance->guest_designation)->toBe('Vendor PIC');
});

it('prevents duplicate guest check-in with same name', function () {
    Attendance::create([
        'booking_id' => $this->booking->id,
        'user_id' => null,
        'guest_name' => 'John External',
        'guest_from' => 'Acme Corp',
        'guest_designation' => 'Vendor PIC',
        'checked_in_at' => now(),
    ]);

    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->assertSet('isGuest', true)
        ->assertSee('Guest Check-In')
        ->set('guestName', 'John External')
        ->set('guestFrom', 'Acme Corp')
        ->set('guestDesignation', 'Vendor PIC')
        ->call('checkIn')
        ->assertSet('alreadyCheckedIn', true)
        ->assertSee('Already Checked In');
});

it('requires guest name when checking in', function () {
    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->assertSet('isGuest', true)
        ->call('checkIn')
        ->assertHasErrors('guestName');
});

it('allows guest check-in with only name (from and designation optional)', function () {
    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->assertSet('isGuest', true)
        ->set('guestName', 'Minimal Guest')
        ->call('checkIn')
        ->assertSet('checkedIn', true);

    expect(Attendance::where('booking_id', $this->booking->id)
        ->where('guest_name', 'Minimal Guest')
        ->exists()
    )->toBeTrue();
});
```

- [ ] **Step 2: Run the new tests**

Run: `php artisan test --filter=AttendanceCheckin`
Expected: All 9 tests pass (5 existing + 4 new)

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/AttendanceCheckinTest.php
git commit -m "feat: add guest check-in tests"
```

---

### Task 11: Final Verification

- [ ] **Step 1: Run full test suite**

Run: `php artisan test`
Expected: All tests pass

- [ ] **Step 2: Check migration status**

Run: `php artisan migrate:status`
Expected: All migrations up, including the new guest fields migration

- [ ] **Step 3: Verify application boots without errors**

Run: `php artisan route:list`
Expected: Routes load without errors

- [ ] **Step 4: Final commit if any fixes were needed**

```bash
git add -A
git commit -m "chore: fix issues from guest attendance implementation"
```

---

## File Change Summary

| # | File | Action |
|---|---|---|
| 1 | `database/migrations/2026_06_05_000001_add_guest_fields_to_attendance_table.php` | Create |
| 2 | `app/Models/Attendance.php` | Modify |
| 3 | `database/factories/AttendanceFactory.php` | Modify |
| 4 | `routes/web.php` | Modify |
| 5 | `app/Livewire/AttendanceCheckin.php` | Modify |
| 6 | `resources/views/livewire/attendance-checkin.blade.php` | Modify |
| 7 | `app/Filament/Resources/Attendances/Schemas/AttendanceForm.php` | Modify |
| 8 | `app/Filament/Resources/Attendances/Tables/AttendancesTable.php` | Modify |
| 9 | `app/Filament/Resources/Bookings/RelationManagers/AttendanceRelationManager.php` | Modify |
| 10 | `tests/Feature/AttendanceCheckinTest.php` | Modify |

## Self-Review

- **Spec coverage:** All spec sections are covered — schema migration (Task 1), model changes (Task 2), factory (Task 3), unauthenticated route (Task 4), QR guest flow (Task 5-6), Filament form (Task 7), Filament table (Task 8), relation manager (Task 9), tests (Task 10).
- **No placeholders:** All code blocks contain complete, working code — no TBDs or TODOs.
- **Type consistency:** All property names (`guestName`, `guestFrom`, `guestDesignation`, `attendee_type`, `guest_name`, `guest_from`, `guest_designation`) are consistent across model, component, view, and tests.
