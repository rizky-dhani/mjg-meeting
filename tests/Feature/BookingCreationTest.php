<?php

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;

beforeEach(function () {
    Role::create(['name' => 'User']);
    Role::create(['name' => 'Admin']);

    $this->room = Room::factory()->create();
    $this->user = User::factory()->create()->assignRole('User');
});

it('a user can create a booking via the model', function () {
    $booking = Booking::create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
        'title' => 'Team Standup',
        'description' => 'Daily team sync',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    expect($booking->title)->toBe('Team Standup');
    assertDatabaseHas('bookings', [
        'title' => 'Team Standup',
        'user_id' => $this->user->id,
    ]);
});

it('a guest cannot create a booking', function () {
    $response = get('/dashboard/bookings/create');

    $response->assertRedirect();
});
