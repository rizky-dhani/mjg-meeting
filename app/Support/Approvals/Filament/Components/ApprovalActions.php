<?php

namespace App\Support\Approvals\Filament\Components;

use App\Support\Approvals\Approval\SimpleApprovalBy;
use App\Support\Approvals\Concerns\HandlesApprovals;
use App\Support\Approvals\Contracts\Approvable;
use App\Support\Approvals\Contracts\ApprovalBy;
use App\Support\Approvals\Contracts\ApprovalFlow;
use App\Support\Approvals\Contracts\HasApprovalStatuses;
use App\Support\Approvals\Enums\ApprovalState;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ApprovalActions extends Component
{
    use HandlesApprovals;

    protected string $approvalKey;

    protected string $view = 'filament::components.section';

    final public function __construct(string $approvalKey)
    {
        $this->approvalKey = $approvalKey;
    }

    public static function make(string $approvalKey): static
    {
        $static = app(static::class, ['approvalKey' => $approvalKey]);
        $static->setUp();

        return $static;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->schema(function () {
            $record = $this->getRecord();

            if (! $record instanceof Approvable) {
                return [];
            }

            $flow = $record->getApprovalFlow($this->approvalKey);

            if ($flow === null || $flow->isDisabled()) {
                return [];
            }

            return $this->buildFlowComponents($flow);
        });
    }

    protected function buildFlowComponents(ApprovalFlow $flow): array
    {
        $components = [];

        foreach ($flow->getApprovalBys() as $approvalBy) {
            $components[] = $this->buildApprovalByGroup($approvalBy);
        }

        // Overall state summary
        $components[] = $this->buildStateSummary($flow);

        return $components;
    }

    protected function buildApprovalByGroup(ApprovalBy $approvalBy): Group
    {
        $record = $this->getRecord();
        $actions = [];
        $currentStatus = $this->getCurrentApprovalStatus($approvalBy);
        $canApprove = $approvalBy->canApprove(Auth::user(), $record);

        foreach ($approvalBy->getApprovalFlow($record, $this->approvalKey)->getApprovalStatus() as $status) {
            $actions[] = $this->buildStatusAction($status, $approvalBy, $currentStatus, $canApprove);
        }

        $label = $approvalBy->getLabel() ?? $approvalBy->getName();

        return Group::make()
            ->schema([
                TextEntry::make("_{$approvalBy->getName()}_title")
                    ->state(ucfirst($label))
                    ->label('')
                    ->weight('bold')
                    ->size('sm'),
                ...$actions,
            ]);
    }

    protected function buildStatusAction(
        HasApprovalStatuses $status,
        ApprovalBy $approvalBy,
        ?HasApprovalStatuses $currentStatus,
        bool $canApprove
    ): Action {
        $isActive = $currentStatus !== null && $currentStatus->value === $status->value;
        $isApprovedStatus = in_array($status, $status::getApprovedStatuses());
        $isDeniedStatus = in_array($status, $status::getDeniedStatuses());

        $color = match (true) {
            $isActive && $isApprovedStatus => 'success',
            $isActive && $isDeniedStatus => 'danger',
            $isActive => 'warning',
            $isApprovedStatus => 'success',
            $isDeniedStatus => 'danger',
            default => 'gray',
        };

        $icon = match (true) {
            $isActive && $isApprovedStatus => 'heroicon-o-check-circle',
            $isActive && $isDeniedStatus => 'heroicon-o-x-circle',
            $isActive => 'heroicon-o-clock',
            $isApprovedStatus => 'heroicon-o-hand-thumb-up',
            $isDeniedStatus => 'heroicon-o-hand-thumb-down',
            default => 'heroicon-o-ellipsis-horizontal-circle',
        };

        $labelText = $status::getCaseLabel($status);

        return Action::make("{$approvalBy->getName()}-{$status->value}")
            ->label($labelText)
            ->icon($icon)
            ->color($color)
            ->visible($canApprove)
            ->disabled($isActive)
            ->requiresConfirmation()
            ->action(function () use ($status, $approvalBy) {
                $this->changeApproval($status, $approvalBy);
            });
    }

    protected function buildStateSummary(ApprovalFlow $flow): TextEntry
    {
        $record = $this->getRecord();
        $state = $flow->approved($record, $this->approvalKey);

        $stateLabel = match ($state) {
            ApprovalState::APPROVED => 'Approved',
            ApprovalState::DENIED => 'Denied',
            ApprovalState::PENDING => 'Pending Approval',
            ApprovalState::OPEN => 'Awaiting Action',
        };

        $stateColor = match ($state) {
            ApprovalState::APPROVED => 'success',
            ApprovalState::DENIED => 'danger',
            ApprovalState::PENDING => 'warning',
            ApprovalState::OPEN => 'gray',
        };

        return TextEntry::make('_approval_state')
            ->label('Overall Status')
            ->state($stateLabel)
            ->badge()
            ->color($stateColor);
    }

    protected function getCurrentApprovalStatus(ApprovalBy $approvalBy): ?HasApprovalStatuses
    {
        return $this->getCurrentStatus($approvalBy, $this->approvalKey);
    }

    protected function changeApproval(HasApprovalStatuses $status, ApprovalBy $approvalBy): void
    {
        $record = $this->getRecord();

        if (! $approvalBy->canApprove(Auth::user(), $record)) {
            abort(403);
        }

        $this->createApproval($status, $approvalBy, $this->approvalKey);
        $this->getRecord()?->refresh();

        Notification::make()
            ->title("Status changed to {$status::getCaseLabel($status)}")
            ->success()
            ->send();
    }

    public function getRecord(bool $withContainerRecord = true): ?Model
    {
        return parent::getRecord($withContainerRecord);
    }
}
