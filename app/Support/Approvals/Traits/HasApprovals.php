<?php

namespace App\Support\Approvals\Traits;

use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Enums\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;

/** @property \Illuminate\Support\Collection $approvals */
trait HasApprovals
{
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function getApprovalFlow(string $key): ?ApprovalFlow
    {
        return $this->getApprovalFlows()[$key] ?? null;
    }

    public function approved(?array $categories = null, ?array $keys = null): ApprovalState
    {
        $flows = $this->getFilteredApprovalFlow($categories, $keys);
        $isPending = false;
        $isOpen = false;

        foreach ($flows as $key => $flow) {
            $state = $flow->approved($this, $key);

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

    public function isApproved(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::APPROVED;
    }

    public function isDenied(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::DENIED;
    }

    public function isPending(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::PENDING;
    }

    public function isOpen(?array $categories = null, ?array $keys = null): bool
    {
        return $this->approved($categories, $keys) === ApprovalState::OPEN;
    }

    public function getFilteredApprovalFlow(?array $categories = null, ?array $keys = null): array
    {
        $flows = $this->getApprovalFlows();

        if ($keys !== null) {
            $flows = Arr::only($flows, $keys);
        }

        if ($categories !== null) {
            $flows = Arr::where($flows, fn(ApprovalFlow $flow) => in_array($flow->getCategory(), $categories));
        }

        return $flows;
    }

    public function approvalStatistics(?array $categories = null, ?array $keys = null): array
    {
        $flows = $this->getFilteredApprovalFlow($categories, $keys);
        $statistics = [];

        foreach ($flows as $key => $flow) {
            $byStatistics = [];

            foreach ($flow->getApprovalBys() as $approvalBy) {
                $approvals = $approvalBy->getApprovals($this, $key);
                $states = $approvals->pluck('status')->map(fn($s) => $s instanceof \BackedEnum ? $s->value : $s);

                $byStatistics[$approvalBy->getName()] = [
                    'reached_at_least' => $approvalBy->reachAtLeast($this, $key),
                    'statuses' => $states->values()->toArray(),
                    'count' => $approvals->count(),
                ];
            }

            $statistics[$key] = [
                'category' => $flow->getCategory(),
                'by_statistics' => $byStatistics,
            ];
        }

        return $statistics;
    }
}
