<?php

namespace App\Support\Approvals\Approval;

use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use App\Support\Approvals\Enums\ApprovalState;
use Closure;
use Illuminate\Database\Eloquent\Model;

class SimpleApprovalFlow implements ApprovalFlow
{
    protected string $category = 'default';

    /** @var array<ApprovalBy> */
    protected array $approvalBys = [];

    /** @var HasApprovalStatuses[] */
    protected array $approvalStatuses = [];

    /** @var null|class-string<HasApprovalStatuses> */
    protected ?string $statusEnumClass = null;

    protected bool|Closure $disabled = false;

    public static function make(): static
    {
        return new static();
    }

    public function disabled(bool|Closure $disabled = true): static
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->disabled instanceof Closure
            ? (bool) ($this->disabled)()
            : $this->disabled;
    }

    /** @param HasApprovalStatuses[] $statuses */
    public function approvalStatus(array $statuses): static
    {
        $this->approvalStatuses = $statuses;
        $this->statusEnumClass = !empty($statuses) ? $statuses[0]::class : null;

        return $this;
    }

    /** @param array<ApprovalBy> $bys */
    public function approvalBys(array $bys): static
    {
        $this->approvalBys = $bys;

        return $this;
    }

    public function category(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getStatusEnumClass(): ?string
    {
        return $this->statusEnumClass;
    }

    public function getApprovalStatus(): array
    {
        return $this->approvalStatuses;
    }

    public function getApprovalBys(): array
    {
        return $this->approvalBys;
    }

    public function approved(Model|Approvable $approvable, string $key): ApprovalState
    {
        if ($this->isDisabled()) {
            return ApprovalState::APPROVED;
        }

        $isPending = false;
        $isOpen = false;

        foreach ($this->approvalBys as $approvalBy) {
            $state = $approvalBy->approved($approvable, $key);

            if ($state === ApprovalState::PENDING) {
                $isPending = true;
            } elseif ($state === ApprovalState::OPEN) {
                $isOpen = true;
            } elseif ($state === ApprovalState::DENIED) {
                return ApprovalState::DENIED;
            }
        }

        if ($isPending) {
            return ApprovalState::PENDING;
        }

        if ($isOpen) {
            return ApprovalState::OPEN;
        }

        return ApprovalState::APPROVED;
    }
}
