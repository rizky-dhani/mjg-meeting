<?php

namespace App\Filament\Resources\ApprovalFlows\Tables;

use App\Models\ApprovalFlow;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalFlowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('model_type')
                    ->badge()
                    ->label('Model')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->color('gray')
                    ->searchable(),

                TextColumn::make('stepCount')
                    ->label('Steps')
                    ->sortable(),

                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
