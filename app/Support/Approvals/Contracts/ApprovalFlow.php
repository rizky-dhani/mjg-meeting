<?php

namespace App\Support\Approvals\Contracts;

use App\Support\Approvals\Enums\ApprovalState;
use Closure;
use Illuminate\Database\Eloquent\Model;

interface ApprovalFlow
{
    public function disabled(bool|Closure $disabled): static;

    public function getCategory(): string;

    /** @return null|class-string<HasApprovalStatuses> */
    public function getStatusEnumClass(): ?string;

    /** @return HasApprovalStatuses[] */
    public function getApprovalStatus(): array;

    public function approved(Model|Approvable $approvable, string $key): ApprovalState;

    public function isDisabled(): bool;

    /** @return array<ApprovalBy> */
    public function getApprovalBys(): array;

    /** @param array<ApprovalBy> $bys */
    public function approvalBys(array $bys): static;
}
