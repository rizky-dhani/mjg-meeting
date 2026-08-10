<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    /**
     * Model keeps division_id as primary + division_user pivot for extras.
     * Single multi-select submits a flat list: first entry becomes the
     * primary, the rest sync into the pivot.
     */
    public static function splitSubmittedDivisions(array $data): array
    {
        $selected = $data['divisions'] ?? [];

        if ($selected === []) {
            $data['division_id'] = null;

            return $data;
        }

        $data['division_id'] = array_shift($selected);
        $data['divisions'] = array_values($selected);

        return $data;
    }

    /**
     * Prime the multi-select state with the primary division so it shows
     * in the UI as one combined list.
     */
    public static function mergePrimaryDivision(array $data, User $record): array
    {
        $selected = $data['divisions'] ?? [];

        if ($record->division_id !== null && ! in_array($record->division_id, $selected, true)) {
            $selected[] = $record->division_id;
        }

        $data['divisions'] = array_values($selected);

        return $data;
    }
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account Information')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->required()
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                            ]),
                    ]),
                Section::make('Employee Details')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('divisions')
                                    ->relationship('divisions', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->label('Division(s)')
                                    ->columnSpanFull(),
                                TextInput::make('employee_code')
                                    ->label('Employee Number')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),
                                Select::make('designation_id')
                                    ->relationship('designation', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->label('Designation'),
                                TextInput::make('initial')
                                    ->maxLength(10),
                            ]),
                    ]),
                Section::make('Roles & Permissions')
                    ->columnSpanFull()
                    ->schema([
                        Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
