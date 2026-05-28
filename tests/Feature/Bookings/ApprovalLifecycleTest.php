<?php

use App\Models\ApprovalFlow;
use App\Models\Booking;
use App\Models\User;
use App\Models\Room;
use App\Support\Approvals\Evaluation\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Spatie\Permission\Models\Role;

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

    $this->admin = User::factory()->create()->assignRole('Admin');
    $this->user = User::factory()->create()->assignRole('User');
    $this->room = Room::factory()->create();

    $this->booking = Booking::factory()->create([
        'room_id' => $this->room->id,
        'user_id' => $this->user->id,
    ]);
});

test('new booking is in open state', function () {
    expect($this->booking->approvalState())->toBe(ApprovalState::Open);
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
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'User',
    ]);

    $this->booking->refresh();

    expect($this->booking->isPending())->toBeTrue();
    expect($this->booking->isApproved())->toBeFalse();
});

test('admin can fully approve a booking', function () {
    // Step 1: User signs off
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'User',
    ]);

    // Step 2: Admin approves
    $this->actingAs($this->admin);
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'Admin',
    ]);

    $this->booking->refresh();

    expect($this->booking->isApproved())->toBeTrue();
});

test('admin can reject a booking', function () {
    // Step 1: User approves
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'User',
    ]);

    // Admin rejects
    $this->actingAs($this->admin);
    Approval::create([
        'approver_id' => $this->admin->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'rejected',
        'key' => 'Booking Approval',
        'approval_by' => 'Admin',
    ]);

    $this->booking->refresh();

    expect($this->booking->isDenied())->toBeTrue();
});

test('non-admin user cannot approve admin step', function () {
    $this->actingAs($this->user);
    $step = $this->booking->currentActionableStep();

    expect($step)->not->toBeNull();
    expect($step->role->name)->toBe('User');

    // The admin step should not be actionable yet
    $adminStep = $this->booking->approvalFlow()->steps->where('role.name', 'Admin')->first();
    expect($adminStep->step_order)->not->toBe($step->step_order);
});

test('admin can approve when it is their turn', function () {
    // Step 1: User approves first
    Approval::create([
        'approver_id' => $this->user->id,
        'approver_type' => User::class,
        'approvable_id' => $this->booking->id,
        'approvable_type' => Booking::class,
        'status' => 'approved',
        'key' => 'Booking Approval',
        'approval_by' => 'User',
    ]);

    $this->booking->refresh();

    // Now admin step should be actionable
    $this->actingAs($this->admin);
    $step = $this->booking->currentActionableStep();

    expect($step)->not->toBeNull();
    expect($step->role->name)->toBe('Admin');
});
