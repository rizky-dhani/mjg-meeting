<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
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
                                Select::make('division_id')
                                    ->relationship('division', 'name')
                                    ->searchable()
                                    ->preload()
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
