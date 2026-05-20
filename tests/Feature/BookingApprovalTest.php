<?php

use App\Filament\Resources\Bookings\Tables\BookingsTable;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

beforeEach(function () {
    Role::create(['name' => 'User']);
    Role::create(['name' => 'Admin']);

    $this->room = Room::factory()->create();
    $this->admin = User::factory()->create()->assignRole('Admin');
    $this->user = User::factory()->create()->assignRole('User');
});

it('admin can approve a pending booking', function () {
    $booking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin);

    expect($booking->status)->toBe('pending');

    BookingsTable::approveBooking($booking);

    $booking->refresh();
    expect($booking->status)->toBe('approved');
    expect($booking->qr_token)->not->toBeNull();
    expect($booking->qr_code)->not->toBeNull();
    expect($booking->approved_by)->toBe($this->admin->id);

    // Booker should be auto-checked-in
    assertDatabaseHas('attendance', [
        'booking_id' => $booking->id,
        'user_id' => $this->user->id,
    ]);
});

it('admin can reject a pending booking', function () {
    $booking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
        'status' => 'pending',
    ]);

    actingAs($this->admin);

    BookingsTable::rejectBooking($booking, ['reason' => 'Room unavailable']);

    $booking->refresh();
    expect($booking->status)->toBe('rejected');
});
