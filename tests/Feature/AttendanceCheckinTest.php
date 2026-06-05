<?php

use App\Livewire\AttendanceCheckin;
use App\Models\ApprovalFlow;
use App\Models\Attendance;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Support\Approvals\Models\Approval;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Role::create(['name' => 'User']);
    Role::create(['name' => 'Admin']);

    $userRole = Role::where('name', 'User')->first();
    $adminRole = Role::where('name', 'Admin')->first();

    $flow = ApprovalFlow::create([
        'name' => 'Booking Approval',
        'model_type' => Booking::class,
    ]);

    $flow->steps()->createMany([
        ['role_id' => $userRole->id, 'step_order' => 1],
        ['role_id' => $adminRole->id, 'step_order' => 2],
    ]);

    $this->userStep = $flow->steps()->where('step_order', 1)->first();
    $this->adminStep = $flow->steps()->where('step_order', 2)->first();

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

    // Step 1: User approves (requester)
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'User',
        'approval_flow_step_id' => $this->userStep->id,
    ]);

    // Step 2: Admin approves
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'Admin',
        'approval_flow_step_id' => $this->adminStep->id,
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
        ->call('confirmCheckIn')
        ->assertSet('confirming', true)
        ->call('checkIn')
        ->assertSet('checkedIn', true)
        ->assertSet('confirming', false);

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
        'date' => now()->subDays(2)->format('Y-m-d'),
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
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'User',
        'approval_flow_step_id' => $this->userStep->id,
    ]);

    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $pastBooking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'Admin',
        'approval_flow_step_id' => $this->adminStep->id,
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

it('loads page for unauthenticated guest users', function () {
    $this->get(route('attendance.checkin', ['qrToken' => $this->booking->qr_token]))
        ->assertOk();
});

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
