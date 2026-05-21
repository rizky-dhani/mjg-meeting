<?php

use App\Models\Booking;
use App\Models\User;
use App\Models\Room;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'User']);
    Role::create(['name' => 'Admin']);

    $this->admin = User::factory()->create()->assignRole('Admin');

    $this->user = User::factory()->create()->assignRole('User');

    $this->room = Room::factory()->create();

    $this->booking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
    ]);
});

test('new booking is in open state', function () {
    expect($this->booking->approved())->toBe(ApprovalState::OPEN);
    expect($this->booking->isOpen())->toBeTrue();
    expect($this->booking->isApproved())->toBeFalse();
    expect($this->booking->isPending())->toBeFalse();
});

test('requester can submit approval as pending', function () {
    $this->actingAs($this->user);

    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    $this->booking->refresh();

    expect($this->booking->isPending())->toBeTrue();
    expect($this->booking->isApproved())->toBeFalse();
});

test('admin can fully approve a booking', function () {
    // Requester signs off
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Approved->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    // Admin approves
    $this->actingAs($this->admin);
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

    expect($this->booking->isApproved())->toBeTrue();
});

test('admin can reject a booking', function () {
    // Requester submits
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Pending->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    // Admin rejects
    $this->actingAs($this->admin);
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Rejected->value,
        'key' => 'booking_approval',
        'approval_by' => 'management',
    ]);

    $this->booking->refresh();

    expect($this->booking->isDenied())->toBeTrue();
});

test('approval flow shows correct statistics', function () {
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => BookingApprovalStatus::Approved->value,
        'key' => 'booking_approval',
        'approval_by' => 'requester',
    ]);

    $stats = $this->booking->approvalStatistics();

    expect($stats)->toHaveKey('booking_approval');
    expect($stats['booking_approval']['by_statistics']['requester']['count'])->toBe(1);
});

test('non-admin user cannot approve via management', function () {
    $this->actingAs($this->user);
    $flow = $this->booking->getApprovalFlow('booking_approval');
    $managementBy = collect($flow->getApprovalBys())
        ->first(fn($by) => $by->getName() === 'management');

    expect($managementBy->canApprove($this->user, $this->booking))->toBeFalse();
});

test('admin can approve via management', function () {
    $this->actingAs($this->admin);
    $flow = $this->booking->getApprovalFlow('booking_approval');
    $managementBy = collect($flow->getApprovalBys())
        ->first(fn($by) => $by->getName() === 'management');

    expect($managementBy->canApprove($this->admin, $this->booking))->toBeTrue();
});
