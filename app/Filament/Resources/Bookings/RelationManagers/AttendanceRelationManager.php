<?php

namespace App\Filament\Resources\Bookings\RelationManagers;

use App\Filament\Resources\Attendances\AttendanceResource;
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
                TextColumn::make('user.name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.initials')
                    ->label('Initials')
                    ->sortable(),
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
