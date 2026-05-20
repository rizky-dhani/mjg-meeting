<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Assignment')
                    ->schema([
                        Select::make('user_id')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('department_id')
                            ->relationship('department', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Work Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('employee_number')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique(ignoreRecord: true),
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
                    ]),
            ]);
    }
}
