<?php

use App\Livewire\AttendanceCheckin;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Models\Approval;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::create(['name' => 'User']);
    Role::create(['name' => 'Admin']);

    $this->room = Room::factory()->create();
    $this->user = User::factory()->create()->assignRole('User');
    $this->admin = User::factory()->create()->assignRole('Admin');

    $qrToken = (string) Str::uuid();
    $this->booking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
        'qr_token' => $qrToken,
        'qr_code' => url('/attendance/' . $qrToken),
    ]);

    // Create requester approval
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    // Admin fully approves
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Approved->value,
        'key' => 'booking_approval',
        'approval_by' => 'management',
    ]);

    $this->booking->refresh();
});

it('shows meeting details for a valid QR token', function () {
    actingAs($this->user);

    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->assertSet('booking.id', $this->booking->id)
        ->assertSee($this->booking->title)
        ->assertSee($this->booking->room->name);
});

it('allows user to check in', function () {
    actingAs($this->user);

    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->call('checkIn')
        ->assertSet('checkedIn', true);

    expect(Attendance::where('booking_id', $this->booking->id)
        ->where('user_id', $this->user->id)
        ->exists()
    )->toBeTrue();
});

it('prevents duplicate check-in', function () {
    actingAs($this->user);

    Attendance::create([
        'booking_id' => $this->booking->id,
        'user_id' => $this->user->id,
        'checked_in_at' => now(),
    ]);

    Livewire::test(AttendanceCheckin::class, ['qrToken' => $this->booking->qr_token])
        ->assertSet('alreadyCheckedIn', true)
        ->assertSee('Already Checked In');
});

it('shows expired for past meeting', function () {
    $pastQrToken = (string) Str::uuid();
    $pastBooking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHour(),
        'qr_token' => $pastQrToken,
        'qr_code' => url('/attendance/' . $pastQrToken),
    ]);

    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $pastBooking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $pastBooking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Approved->value,
        'key' => 'booking_approval',
        'approval_by' => 'management',
    ]);

    actingAs($this->user);

    Livewire::test(AttendanceCheckin::class, ['qrToken' => $pastBooking->qr_token])
        ->assertSet('isExpired', true)
        ->assertSee('QR Code Expired');
});

it('shows invalid for non-existent token', function () {
    actingAs($this->user);

    Livewire::test(AttendanceCheckin::class, ['qrToken' => 'non-existent-token'])
        ->assertSet('booking', null)
        ->assertSee('Invalid QR Code');
});

it('requires authentication', function () {
    $this->get(route('attendance.checkin', ['qrToken' => $this->booking->qr_token]))
        ->assertRedirect();
});
