<?php

namespace App\Support\Approvals\Contracts;

use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ApprovalBy
{
    public function approved(Model|Approvable $approvable, string $key): ApprovalState;

    /** @return Collection<int, Approval> */
    public function getApprovals(Model|Approvable $approvable, string $key): Collection;

    public function getName(): string;

    public function getLabel(): ?string;

    public function getApprovalFlow(Model|Approvable $approvable, string $key): ?ApprovalFlow;

    public function canApprove(Approver|Model $approver, Approvable $approvable): bool;

    public function canApproveFromPermissions(Approver|Model $approver): bool;

    public function reachAtLeast(Approvable|Model $approvable, string $key): bool;
}
