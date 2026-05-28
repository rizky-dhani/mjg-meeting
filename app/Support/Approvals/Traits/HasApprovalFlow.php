<?php

namespace App\Support\Approvals\Traits;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Support\Approvals\Evaluation\ApprovalEvaluator;
use App\Support\Approvals\Evaluation\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasApprovalFlow
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function approvalFlow(): ?ApprovalFlow
    {
        return ApprovalEvaluator::findFlow($this);
    }

    public function approvalState(): ApprovalState
    {
        return ApprovalEvaluator::evaluate($this);
    }

    public function isApproved(): bool
    {
        return $this->approvalState() === ApprovalState::Approved;
    }

    public function isDenied(): bool
    {
        return $this->approvalState() === ApprovalState::Denied;
    }

    public function isPending(): bool
    {
        return $this->approvalState() === ApprovalState::Pending;
    }

    public function isOpen(): bool
    {
        return $this->approvalState() === ApprovalState::Open;
    }

    public function currentActionableStep(): ?ApprovalFlowStep
    {
        return ApprovalEvaluator::currentActionableStep($this);
    }
}
