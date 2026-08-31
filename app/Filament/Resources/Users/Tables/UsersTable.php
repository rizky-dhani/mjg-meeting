<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Schemas\UserForm;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['division', 'divisions']))
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
                TextColumn::make('divisions')
                    ->label(__('Division'))
                    ->badge()
                    ->color('gray')
                    ->getStateUsing(fn (User $record): array => $record->allDivisions()->pluck('initial')->all())
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->where(function (Builder $q) use ($search): void {
                            $q->whereHas('division', fn ($dq) => $dq->where('initial', 'like', "%{$search}%"))
                                ->orWhereHas('divisions', fn ($dq) => $dq->where('initial', 'like', "%{$search}%"));
                        });
                    }),
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
                    ->modal()
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => UserForm::mergePrimaryDivision($data, $record))
                    ->mutateFormDataUsing(fn (array $data): array => UserForm::splitSubmittedDivisions($data)),
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
