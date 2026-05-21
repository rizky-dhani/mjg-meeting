<?php

use App\Support\Approvals\Approval\SimpleApprovalBy;

test('simple approval by has name', function () {
    $by = SimpleApprovalBy::make('management');
    expect($by->getName())->toBe('management');
});

test('simple approval by any allows any approver', function () {
    $by = SimpleApprovalBy::make('anyone')->any();
    $approver = Mockery::mock(\App\Support\Approvals\Contracts\Approver::class);
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($by->canApprove($approver, $approvable))->toBeTrue();
});

test('simple approval by any without role denies non-authenticated', function () {
    $by = SimpleApprovalBy::make('restricted');
    $approver = Mockery::mock(\App\Support\Approvals\Contracts\Approver::class);
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($by->canApprove($approver, $approvable))->toBeFalse();
});

test('simple approval by with custom closure', function () {
    $by = SimpleApprovalBy::make('custom')
        ->canApproveUsing(fn($approver, $approvable) => true);
    $approver = Mockery::mock(\App\Support\Approvals\Contracts\Approver::class);
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($by->canApprove($approver, $approvable))->toBeTrue();
});

test('reachAtLeast returns false when under threshold', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);
    $approvable->approvals = collect([
        (object) ['key' => 'test', 'approval_by' => 'mgmt'],
    ]);

    $by = SimpleApprovalBy::make('mgmt')->atLeast(2);

    expect($by->reachAtLeast($approvable, 'test'))->toBeFalse();
});

test('reachAtLeast returns true when at threshold', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);
    $approvable->approvals = collect([
        (object) ['key' => 'test', 'approval_by' => 'mgmt'],
        (object) ['key' => 'test', 'approval_by' => 'mgmt'],
    ]);

    $by = SimpleApprovalBy::make('mgmt')->atLeast(2);

    expect($by->reachAtLeast($approvable, 'test'))->toBeTrue();
});
