<?php

namespace App\Support\Approvals\Concerns;

use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use App\Models\User;
use App\Support\Approvals\Models\Approval;
use Illuminate\Support\Facades\Auth;

trait HandlesApprovals
{
    protected function createApproval(
        HasApprovalStatuses $status,
        ApprovalBy $approvalBy,
        string $key
    ): Approval {
        $record = $this->getRecord();

        return Approval::create([
            'approver_id' => Auth::id(),
            'approver_type' => User::class,
            'approvable_id' => $record->id,
            'approvable_type' => $record::class,
            'status' => $status->value,
            'key' => $key,
            'approval_by' => $approvalBy->getName(),
        ]);
    }

    protected function removeApproval(ApprovalBy $approvalBy, string $key): void
    {
        $this->getBoundApprovals($approvalBy, $key)->each->delete();
    }

    protected function getBoundApprovals(ApprovalBy $approvalBy, string $key)
    {
        $record = $this->getRecord();

        return $record->approvals
            ->where('key', $key)
            ->where('approval_by', $approvalBy->getName())
            ->where('approver_id', Auth::id())
            ->where('approver_type', User::class);
    }

    protected function getCurrentStatus(ApprovalBy $approvalBy, string $key): ?HasApprovalStatuses
    {
        $approval = $this->getBoundApprovals($approvalBy, $key)->first();

        return $approval?->status;
    }

    abstract public function getRecord();
}
