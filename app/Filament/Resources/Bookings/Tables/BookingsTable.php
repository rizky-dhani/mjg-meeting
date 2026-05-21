<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Booking;
use App\Support\Approvals\ApprovalStatus\BookingApprovalStatus;
use App\Support\Approvals\Enums\ApprovalState;
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
use Illuminate\Support\Str;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => static::scopeQuery($query))
            ->columns([
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
                TextColumn::make('starts_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('approval_state')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn(Booking $record): string => match ($record->approved()) {
                        ApprovalState::APPROVED => 'approved',
                        ApprovalState::DENIED => 'rejected',
                        ApprovalState::PENDING => 'pending',
                        ApprovalState::OPEN => 'open',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
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
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn(Booking $record): bool =>
                        auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')),
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Booking $record): bool =>
                        $record->isPending() && (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')))
                    ->requiresConfirmation()
                    ->action(function (Booking $record) {
                        $flow = $record->getApprovalFlow('booking_approval');
                        $managementBy = collect($flow->getApprovalBys())
                            ->first(fn($by) => $by->getName() === 'management');

                        if ($managementBy) {
                            Approval::create([
                                'approver_id' => auth()->id(),
                                'approver_type' => \App\Models\User::class,
                                'approvable_id' => $record->id,
                                'approvable_type' => Booking::class,
                                'status' => BookingApprovalStatus::Approved->value,
                                'key' => 'booking_approval',
                                'approval_by' => 'management',
                            ]);
                        }

                        // If the requester hasn't submitted yet, auto-submit
                        $requesterApproval = $record->approvals
                            ->where('key', 'booking_approval')
                            ->where('approval_by', 'requester')
                            ->first();

                        if (! $requesterApproval) {
                            Approval::create([
                                'approver_id' => $record->user_id,
                                'approver_type' => \App\Models\User::class,
                                'approvable_id' => $record->id,
                                'approvable_type' => Booking::class,
                                'status' => BookingApprovalStatus::Pending->value,
                                'key' => 'booking_approval',
                                'approval_by' => 'requester',
                            ]);
                        }

                        $record->refresh();

                        // If fully approved, generate QR code and notification
                        if ($record->isApproved()) {
                            $qrToken = (string) Str::uuid();
                            $qrCodeUrl = url('/attendance/' . $qrToken);

                            $record->update([
                                'qr_token' => $qrToken,
                                'qr_code' => $qrCodeUrl,
                            ]);

                            // Auto-check-in the booker
                            $record->attendance()->create([
                                'user_id' => $record->user_id,
                                'checked_in_at' => now(),
                            ]);

                            $record->user->notify(new \App\Notifications\BookingApproved($record));
                        }

                        Notification::make()
                            ->title('Booking approved successfully')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Booking $record): bool =>
                        $record->isPending() && (auth()->user()->hasRole('Admin') || auth()->user()->hasRole('Super Admin')))
                    ->requiresConfirmation()
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Reason for rejection')
                            ->required(),
                    ])
                    ->action(function (Booking $record, array $data) {
                        Approval::create([
                            'approver_id' => auth()->id(),
                            'approver_type' => \App\Models\User::class,
                            'approvable_id' => $record->id,
                            'approvable_type' => Booking::class,
                            'status' => BookingApprovalStatus::Rejected->value,
                            'key' => 'booking_approval',
                            'approval_by' => 'management',
                        ]);

                        $record->refresh();

                        $record->user->notify(new \App\Notifications\BookingRejected($record, $data['reason'] ?? null));

                        Notification::make()
                            ->title('Booking rejected')
                            ->warning()
                            ->send();
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

        if ($user->hasRole('Admin') || $user->hasRole('Super Admin')) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }
}
