<?php

namespace App\Support\Approvals\Models;

use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use Error;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Approval extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'key',
        'approvable_type',
        'approvable_id',
        'approver_id',
        'approver_type',
        'approval_by',
        'status',
    ];

    public function approver(): MorphTo
    {
        return $this->morphTo('approver');
    }

    public function approvable(): MorphTo
    {
        return $this->morphTo('approvable');
    }

    public function getStatusAttribute(string|null $value): HasApprovalStatuses|string|null
    {
        if ($value === null) {
            return null;
        }

        try {
            $flow = $this->getApprovalFlow();

            if ($flow === null) {
                return $value;
            }

            return collect($flow->getApprovalStatus())
                ->firstWhere(fn($unitEnum) => $unitEnum->value === $value) ?? $value;
        } catch (Error|Exception) {
            return $value;
        }
    }

    public function setStatusAttribute(HasApprovalStatuses|string $value): void
    {
        $this->attributes['status'] = $value instanceof HasApprovalStatuses ? $value->value : $value;
    }

    protected function getApprovalFlow(): ?ApprovalFlow
    {
        $approvable = $this->approvable;

        if (! $approvable instanceof Approvable) {
            return null;
        }

        return $approvable->getApprovalFlow($this->key);
    }
}
