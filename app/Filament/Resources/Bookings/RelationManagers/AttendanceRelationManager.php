<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Models\Attendance;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendanceRelationManager extends RelationManager
{
    protected static string $relationship = 'attendance';

    protected static ?string $recordTitleAttribute = 'id';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user'))
            ->columns([
                TextColumn::make('attendee_name')
                    ->label('Attendee')
                    ->state(fn (Attendance $record): string =>
                        $record->user?->name ?? $record->guest_name ?? 'N/A'
                    )
                    ->searchable(query: fn ($query, $search) =>
                        $query->where('guest_name', 'like', "%{$search}%")
                            ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    )
                    ->sortable(),
                TextColumn::make('guest_from')
                    ->label('From')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_from
                    ),
                TextColumn::make('guest_designation')
                    ->label('Designation')
                    ->state(fn (Attendance $record): ?string =>
                        $record->user_id ? '—' : $record->guest_designation
                    ),
                TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime('d F Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('checked_in_at', 'desc');
    }

    public static function getRecordTitle(): ?string
    {
        return 'Attendance';
    }

    public static function getTitle(mixed $ownerRecord, string $pageClass): string
    {
        return 'Attendance';
    }
}
