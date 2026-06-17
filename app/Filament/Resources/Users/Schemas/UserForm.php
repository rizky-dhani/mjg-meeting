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
                Section::make('Employee Details')
                    ->schema([
                        TextInput::make('employee_number')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true),
                        Select::make('department_id')
                            ->relationship('department', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('position')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('initials')
                            ->required()
                            ->maxLength(10),
                        TextInput::make('phone')
                            ->maxLength(50)
                            ->tel(),
                    ]),
                Section::make('Roles & Permissions')
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
