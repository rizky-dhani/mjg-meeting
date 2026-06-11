<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Bookings\BookingResource;
use App\Models\Booking;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class BookingsList extends BaseWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        $user = auth()->user();

        if (! $user->department_id) {
            return Booking::whereRaw('0 = 1');
        }

        $departmentUserIds = User::where('department_id', $user->department_id)
            ->pluck('id');

        return Booking::whereIn('user_id', $departmentUserIds)
            ->with('approvals');
    }

    protected function getTableColumns(): array
    {
        return [
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
                ->getStateUsing(fn (Booking $record): string => $record->approvalState()->value)
                ->formatStateUsing(fn (string $state): string => ucfirst($state))
                ->color(fn (string $state): string => match ($state) {
                    'approved' => 'success',
                    'pending' => 'warning',
                    'denied' => 'danger',
                    'open' => 'gray',
                    default => 'gray',
                }),
        ];
    }

    protected function getDefaultTableSortColumn(): ?string
    {
        return 'booking_number';
    }

    protected function getDefaultTableSortDirection(): ?string
    {
        return 'desc';
    }

    protected function getTableRecordUrlUsing(): ?callable
    {
        return fn (Booking $record): string => BookingResource::getUrl('view', ['record' => $record]);
    }
}
