<?php

namespace App\Support\Approvals\Evaluation;

use App\Models\ApprovalFlow;
use App\Models\ApprovalFlowStep;
use App\Models\User;
use App\Support\Approvals\Models\Approval;
use Illuminate\Database\Eloquent\Model;

class ApprovalEvaluator
{
    /**
     * Evaluate the approval state for a given model instance.
     *
     * Steps are evaluated sequentially. If a step has no approval record,
     * the state is Open and the actionable step is returned.
     */
    public static function evaluate(Model $model): ApprovalState
    {
        $flow = static::findFlow($model);

        if ($flow === null || $flow->steps->isEmpty()) {
            return ApprovalState::Approved;
        }

        $hasAnyAction = false;

        foreach ($flow->steps as $step) {
            $state = static::checkStep($model, $flow, $step);

            if ($state === ApprovalState::Denied) {
                return ApprovalState::Denied;
            }

            if ($state === ApprovalState::Pending) {
                return ApprovalState::Pending;
            }

            if ($state === ApprovalState::Approved) {
                $hasAnyAction = true;

                continue;
            }

            // Step is open: if a previous step was acted upon, it's pending
            return $hasAnyAction ? ApprovalState::Pending : ApprovalState::Open;
        }

        return ApprovalState::Approved;
    }

    /**
     * Get the first step that hasn't been approved yet.
     * Returns null if all steps are approved.
     */
    public static function currentActionableStep(Model $model): ?ApprovalFlowStep
    {
        $flow = static::findFlow($model);

        if ($flow === null) {
            return null;
        }

        foreach ($flow->steps as $step) {
            if (! static::isStepApproved($model, $flow, $step)) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Find the ApprovalFlow for the given model type.
     */
    public static function findFlow(Model $model): ?ApprovalFlow
    {
        return ApprovalFlow::where('model_type', $model::class)->with('steps.role', 'steps.department')->first();
    }

    /**
     * Determine whether a step should be auto-skipped.
     * When scope=requester and no user in the requester's department has the
     * required role, the step cannot be fulfilled — skip it automatically.
     */
    protected static function shouldAutoSkipStep(Model $model, ApprovalFlowStep $step): bool
    {
        if ($step->scope !== ApprovalFlowStep::SCOPE_REQUESTER) {
            return false;
        }

        $requester = $model->user;

        if ($requester === null || $requester->department_id === null) {
            return true;
        }

        return ! User::where('department_id', $requester->department_id)
            ->role($step->role->name)
            ->exists();
    }

    /**
     * Check approval records for a specific step.
     */
    protected static function checkStep(Model $model, ApprovalFlow $flow, ApprovalFlowStep $step): ApprovalState
    {
        // Auto-skip if scope=requester but no eligible approver exists in the requester's department
        if (static::shouldAutoSkipStep($model, $step)) {
            return ApprovalState::Approved;
        }

        $approvals = static::getApprovalsForStep($model, $flow, $step);

        // If no approval records exist, the step is open
        if ($approvals->isEmpty()) {
            return ApprovalState::Open;
        }

        // Check if any record has a denied/rejected status
        $deniedStatuses = ['denied', 'rejected'];
        if ($approvals->contains(fn (Approval $a) => in_array($a->getRawOriginal('status'), $deniedStatuses))) {
            return ApprovalState::Denied;
        }

        // Check if there's an approved record
        $approvedStatuses = ['approved'];
        if ($approvals->contains(fn (Approval $a) => in_array($a->getRawOriginal('status'), $approvedStatuses))) {
            return ApprovalState::Approved;
        }

        // Records exist but neither approved nor denied → pending
        return ApprovalState::Pending;
    }

    /**
     * Check if a specific step has been approved.
     */
    protected static function isStepApproved(Model $model, ApprovalFlow $flow, ApprovalFlowStep $step): bool
    {
        // Auto-skip if scope=requester but no eligible approver exists
        if (static::shouldAutoSkipStep($model, $step)) {
            return true;
        }

        $approvals = static::getApprovalsForStep($model, $flow, $step);

        $approvedStatuses = ['approved'];
        return $approvals->contains(fn (Approval $a) => in_array($a->getRawOriginal('status'), $approvedStatuses));
    }

    /**
     * Get approval records for a specific step.
     */
    protected static function getApprovalsForStep(Model $model, ApprovalFlow $flow, ApprovalFlowStep $step)
    {
        return $model->approvals
            ->where('key', $flow->name)
            ->where('approval_flow_step_id', $step->id);
    }
}
