<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Models\Attendance;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('booking.title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                TextColumn::make('attendee_name')
                    ->label('Attendee')
                    ->searchable(query: fn ($query, $search) =>
                        $query->where('guest_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    )
                    ->sortable(query: fn ($query, $direction) =>
                        $query->orderBy('guest_name', $direction)
                    )
                    ->state(fn (Attendance $record): string =>
                        $record->user?->name ?? $record->guest_name ?? 'N/A'
                    ),
                TextColumn::make('guest_from')
                    ->label('From')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_from
                    )
                    ->sortable(),
                TextColumn::make('guest_designation')
                    ->label('Designation')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_designation
                    )
                    ->sortable(),
                TextColumn::make('attendee_type')
                    ->label('Type')
                    ->state(fn (Attendance $record): string => $record->attendee_type)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'staff' => 'success',
                        'guest' => 'warning',
                    }),
                TextColumn::make('checked_in_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                TextColumn::make('booking.starts_at')
                    ->dateTime('M d, Y H:i')
                    ->label('Meeting Date')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                //
            ])
            ->defaultSort('checked_in_at', 'desc');
    }
}
