<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\ApprovalFlow;
use App\Models\Booking;
use App\Models\User;
use App\Support\Approvals\Evaluation\ApprovalState;
use App\Support\Approvals\Models\Approval;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Milon\Barcode\Facades\DNS2DFacade;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => static::scopeQuery($query))
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
                    ->state(fn (Booking $record): string => $record->starts_at->format('H:i') . ' - ' . $record->ends_at->format('H:i'))
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
                    ->getStateUsing(fn(Booking $record): string => $record->approvalState()->value)
                    ->formatStateUsing(fn(string $state): string => ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
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
                    ->visible(fn(Booking $record): bool =>
                        ! auth()->user()->hasRole('Head')),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Booking $record): bool => static::canApproveStep($record))
                    ->requiresConfirmation()
                    ->action(function (Booking $record) {
                        static::processApproval($record, 'approved');
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Booking $record): bool => static::canApproveStep($record))
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason for rejection')
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data) {
                        static::processApproval($record, 'rejected');

                        $record->user->notify(
                            new \App\Notifications\BookingRejected($record, $data['reason'] ?? null)
                        );
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')),
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

        // Head: sees department-scoped bookings for approval
        if ($user->hasRole('Head')) {
            $departmentUserIds = User::where('department_id', $user->department_id)
                ->pluck('id');

            return $query->whereIn('booker_id', $departmentUserIds);
        }

        // Everyone else (Admin, etc.): only sees their own bookings
        return $query->where('booker_id', $user->id);
    }

    protected static function canApproveStep(Booking $record): bool
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
            // Specific department: user must belong to that department
            'department' => $step->department !== null && $user->department_id === $step->department->id,

            // Same as requester: user must be in the requester's department
            'requester' => $user->department_id === $record->user->department_id,

            // All departments: no additional check needed
            default => true,
        };
    }

    protected static function processApproval(Booking $record, string $status): void
    {
        $step = $record->currentActionableStep();

        if ($step === null || $step->role === null) {
            Notification::make()
                ->title('No pending steps to approve')
                ->warning()
                ->send();

            return;
        }

        $flow = $record->approvalFlow();

        if ($flow === null) {
            return;
        }

        Approval::create([
            'approver_id' => auth()->id(),
            'approver_type' => \App\Models\User::class,
            'approvable_id' => $record->id,
            'approvable_type' => Booking::class,
            'status' => $status,
            'key' => $flow->name,
            'approval_by' => $step->role->name,
            'approval_flow_step_id' => $step->id,
        ]);

        $record->refresh();

        if ($status === 'approved' && $record->isApproved()) {
            $qrToken = (string) Str::uuid();
            $qrCodeUrl = url('/attendance/' . $qrToken);

            $qrPng = DNS2DFacade::getBarcodePNG($qrCodeUrl, 'QRCODE', 8, 8);
            $qrPath = sprintf('bookings/QR-%s.png', $record->booking_number);
            Storage::disk('public')->put($qrPath, $qrPng);

            $record->update([
                'qr_token' => $qrToken,
                'qr_code' => $qrPath,
            ]);

            $record->attendance()->create([
                'user_id' => $record->user_id,
                'checked_in_at' => now(),
            ]);

            $record->user->notify(new \App\Notifications\BookingApproved($record));
        }

        Notification::make()
            ->title($status === 'approved' ? 'Booking approved successfully' : 'Booking rejected')
            ->{$status === 'approved' ? 'success' : 'warning'}()
            ->send();
    }
}
