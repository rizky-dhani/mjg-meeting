<?php

use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Approval\SimpleApprovalFlow;
use App\Support\Approvals\Enums\ApprovalState;

beforeEach(function () {
    $this->flow = SimpleApprovalFlow::make();
});

test('disabled flow always returns approved', function () {
    $flow = SimpleApprovalFlow::make()->disabled();
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($flow->approved($approvable, 'test'))->toBe(ApprovalState::APPROVED);
});

test('flow with no approval bys returns approved', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::APPROVED);
});

test('flow returns denied when any approvalBy is denied', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);
    $approvable->allows('approvals')->andReturn(collect());

    $deniedBy = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $deniedBy->allows('approved')->andReturn(ApprovalState::DENIED);

    $this->flow->approvalBys([$deniedBy]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::DENIED);
});

test('flow returns pending when any approvalBy is pending and none denied', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    $pendingBy = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $pendingBy->allows('approved')->andReturn(ApprovalState::PENDING);

    $approvedBy = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $approvedBy->allows('approved')->andReturn(ApprovalState::APPROVED);

    $this->flow->approvalBys([$pendingBy, $approvedBy]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::PENDING);
});

test('flow returns approved when all approvalBys are approved', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    $by1 = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $by1->allows('approved')->andReturn(ApprovalState::APPROVED);

    $by2 = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $by2->allows('approved')->andReturn(ApprovalState::APPROVED);

    $this->flow->approvalBys([$by1, $by2]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::APPROVED);
});

test('flow returns open when all approvalBys are open', function () {
    $approvable = Mockery::mock(\App\Support\Approvals\Contracts\Approvable::class);

    $by1 = Mockery::mock(\App\Support\Approvals\Contracts\ApprovalBy::class);
    $by1->allows('approved')->andReturn(ApprovalState::OPEN);

    $this->flow->approvalBys([$by1]);

    expect($this->flow->approved($approvable, 'test'))->toBe(ApprovalState::OPEN);
});
