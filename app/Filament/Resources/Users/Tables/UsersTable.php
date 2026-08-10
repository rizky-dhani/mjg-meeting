<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label(__('Email'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee_code')
                    ->label(__('Employee Number'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('division.initial')
                    ->label(__('Division'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('designation.name')
                    ->label(__('Designation'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('roles.name')
                    ->label(__('Role'))
                    ->badge()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('division_id')
                    ->label('Division')
                    ->relationship('division', 'initial')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modal(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assignRole')
                        ->label('Set Role')
                        ->visible(fn (): bool => auth()->user()?->hasRole('Super Admin') ?? false)
                        ->form([
                            Select::make('role')
                                ->label('Role')
                                ->options(Role::pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each(fn ($record) => $record->syncRoles([$data['role']]));
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
