<?php

namespace App\Filament\Resources\Attendances\Tables;

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
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
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
