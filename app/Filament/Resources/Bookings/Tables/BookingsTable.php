<?php

namespace App\Filament\Resources\Bookings\Tables;

use App\Models\Booking;
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
                TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
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
                    ->action(fn(Booking $record) => static::approveBooking($record)),
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
                    ->action(fn(Booking $record, array $data) => static::rejectBooking($record, $data)),
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

    public static function approveBooking(Booking $booking): void
    {
        $qrToken = (string) Str::uuid();
        $qrCodeUrl = url('/attendance/' . $qrToken);

        $booking->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'qr_token' => $qrToken,
            'qr_code' => $qrCodeUrl,
        ]);

        // Auto-check-in the booker
        $booking->attendance()->create([
            'user_id' => $booking->user_id,
            'checked_in_at' => now(),
        ]);

        // Send notification to booker
        $booking->user->notify(new \App\Notifications\BookingApproved($booking));

        Notification::make()
            ->title('Booking approved successfully')
            ->success()
            ->send();
    }

    public static function rejectBooking(Booking $booking, array $data): void
    {
        $booking->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $booking->user->notify(new \App\Notifications\BookingRejected($booking, $data['reason'] ?? null));

        Notification::make()
            ->title('Booking rejected')
            ->warning()
            ->send();
    }
}
