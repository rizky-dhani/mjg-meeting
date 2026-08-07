<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Position;
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
                                TextInput::make('employee_number')
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),
                                Select::make('position')
                                    ->options(fn () => Position::pluck('name', 'name'))
                                    ->searchable()
                                    ->preload(),
                                TextInput::make('initials')
                                    ->maxLength(10),
                                TextInput::make('phone')
                                    ->maxLength(50)
                                    ->tel(),
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
