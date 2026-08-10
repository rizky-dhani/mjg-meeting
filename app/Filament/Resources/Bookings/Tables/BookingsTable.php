<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\ApprovalFlow;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingApprovalService;
use App\Support\Approvals\Models\Approval;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => static::applyPendingFirstSort(static::scopeQuery($query)))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('booking_number')
                    ->label('Booking #')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('date')
                    ->sortable()
                    ->state(fn (Booking $record): string => strtoupper($record->date->format('d F Y'))),
                TextColumn::make('time')
                    ->label('Time')
                    ->state(fn (Booking $record): string => $record->starts_at->format('H:i').' - '.$record->ends_at->format('H:i'))
                    ->sortable(['starts_at', 'ends_at']),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('room.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->sortable()
                    ->searchable()
                    ->label('Booked by'),
                TextColumn::make('approval_state')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Booking $record): string => $record->approvalState()->value)
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'denied' => 'danger',
                        'open' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(false),
            ])
            ->filters([
                SelectFilter::make('approval_state')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'denied' => 'Denied',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (! $data['value']) {
                            return;
                        }

                        $flow = ApprovalFlow::where('model_type', Booking::class)->first();
                        if (! $flow) {
                            return;
                        }

                        match ($data['value']) {
                            'approved' => $query->whereHas('approvals', function ($q) use ($flow) {
                                $q->where('key', $flow->name);
                            }),
                            'denied' => $query->whereHas('approvals', function ($q) use ($flow) {
                                $q->where('key', $flow->name)
                                    ->whereIn('status', ['denied', 'rejected']);
                            }),
                            'open' => $query->whereDoesntHave('approvals', function ($q) use ($flow) {
                                $q->where('key', $flow->name);
                            }),
                            default => null,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (Booking $record): bool => ! auth()->user()->hasRole('Head')),
                static::getApproveAction(),
                static::getRejectAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')),
                ]),
            ]);
    }

    public static function scopeQuery(Builder $query): Builder
    {
        $user = auth()->user();

        $query->with('approvals');

        // Super Admin: sees all bookings
        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        // Head/Admin: sees bookings from their own division
        if ($user->hasRole('Head') || $user->hasRole('Admin')) {
            $divisionUserIds = User::where('division_id', $user->division_id)
                ->pluck('id');

            return $query->whereIn('booker_id', $divisionUserIds);
        }

        // If the user has a role that matches an approval flow step,
        // they are a potential approver and need visibility into bookings
        // that may require their action. The actual approve/reject action
        // is gated by canApproveStep() which enforces step, scope, and department checks.
        $flow = ApprovalFlow::where('model_type', Booking::class)->first();
        if ($flow) {
            $userRoleNames = $user->getRoleNames();
            $isApprover = $flow->steps()
                ->whereHas('role', function ($q) use ($userRoleNames) {
                    $q->whereIn('name', $userRoleNames);
                })
                ->exists();

            if ($isApprover) {
                return $query;
            }
        }

        // Everyone else: only sees their own bookings
        return $query->where('booker_id', $user->id);
    }

    /**
     * Sort bookings so in-flight (pending) approvals surface first, then
     * newest first. Mirrors ApprovalEvaluator::evaluate(): pending = the
     * flow has started (an approval record exists), nothing has been
     * rejected/denied, and at least one step is not yet approved.
     */
    protected static function applyPendingFirstSort(Builder $query): Builder
    {
        $flow = ApprovalFlow::where('model_type', Booking::class)->first();

        if (! $flow) {
            return $query->orderBy('bookings.created_at', 'desc');
        }

        return $query->orderByRaw('CASE WHEN (
                EXISTS (
                    SELECT 1 FROM approvals a
                    WHERE a.approvable_type = ?
                      AND a.approvable_id = bookings.id
                      AND a.key = ?
                      AND a.deleted_at IS NULL
                )
                AND NOT EXISTS (
                    SELECT 1 FROM approvals a2
                    WHERE a2.approvable_type = ?
                      AND a2.approvable_id = bookings.id
                      AND a2.status IN (?, ?)
                      AND a2.deleted_at IS NULL
                )
                AND EXISTS (
                    SELECT 1 FROM approval_flow_steps s
                    WHERE s.approval_flow_id = ?
                      AND NOT EXISTS (
                          SELECT 1 FROM approvals a3
                          WHERE a3.approvable_type = ?
                            AND a3.approvable_id = bookings.id
                            AND a3.approval_flow_step_id = s.id
                            AND a3.status = ?
                            AND a3.deleted_at IS NULL
                      )
                )
            ) THEN 0 ELSE 1 END ASC, bookings.created_at DESC',
            [Booking::class, $flow->name, Booking::class, 'rejected', 'denied', $flow->id, Booking::class, 'approved']
        );
    }

    public static function canApproveStep(Booking $record): bool
    {
        // Admin: CRUD without Approval
        if (auth()->user()->hasRole('Admin')) {
            return false;
        }

        $step = $record->currentActionableStep();

        if ($step === null || $step->role === null) {
            return false;
        }

        $user = auth()->user();

        if (! $user->hasRole($step->role->name)) {
            return false;
        }

        return match ($step->scope) {
            // Specific divisions: user must belong to one of them
            'department' => $step->divisions->isNotEmpty() && $step->divisions->contains('id', $user->division_id),

            // Same as requester: user must be in the requester's division
            'requester' => $user->division_id === $record->user->division_id,

            // All divisions: no additional check needed
            default => true,
        };
    }

    public static function getApproveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (Booking $record): bool => static::canApproveStep($record))
            ->requiresConfirmation()
            ->action(function (Booking $record) {
                app(BookingApprovalService::class)->approve($record);
            });
    }

    public static function getRejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (Booking $record): bool => static::canApproveStep($record))
            ->requiresConfirmation()
            ->form([
                Textarea::make('reason')
                    ->label('Reason for rejection')
                    ->required(),
            ])
            ->action(function (Booking $record, array $data) {
                app(BookingApprovalService::class)->reject($record, $data['reason'] ?? null);
            });
    }

    public static function processApproval(Booking $record, string $status, ?string $reason = null): void
    {
        $service = app(BookingApprovalService::class);

        if ($status === 'approved') {
            $service->approve($record, $reason);
        } else {
            $service->reject($record, $reason);
        }
    }
}
