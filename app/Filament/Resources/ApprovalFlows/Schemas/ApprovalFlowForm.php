<?php

namespace App\Filament\Resources\ApprovalFlows\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\File;

class ApprovalFlowForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->columns()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Approval Name')
                            ->placeholder('e.g., Booking Approval'),

                        Select::make('model_type')
                            ->required()
                            ->options(function () {
                                $models = [];
                                $files = File::files(app_path('Models'));

                                foreach ($files as $file) {
                                    $class = 'App\\Models\\' . $file->getFilenameWithoutExtension();
                                    if (class_exists($class)) {
                                        $models[$class] = $file->getFilenameWithoutExtension();
                                    }
                                }

                                return $models;
                            })
                            ->searchable()
                            ->preload()
                            ->label('Model Type')
                            ->helperText('The model this approval flow applies to.'),

                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Approval Steps')
                    ->schema([
                        Repeater::make('steps')
                            ->relationship()
                            ->orderColumn('step_order')
                            ->reorderable()
                            ->addActionLabel('Add Step')
                            ->defaultItems(0)
                            ->columns(3)
                            ->schema([
                                Select::make('scope')
                                    ->options([
                                        'all' => 'All Departments',
                                        'department' => 'Specific Department',
                                        'requester' => 'Same as Requester',
                                    ])
                                    ->default('all')
                                    ->required()
                                    ->label('Dept. Scope')
                                    ->live(),

                                Select::make('department_id')
                                    ->relationship('department', 'name', fn (Builder $query) => $query->orderBy('name'))
                                    ->nullable()
                                    ->searchable()
                                    ->preload()
                                    ->label('Department')
                                    ->hidden(fn (Get $get) => $get('scope') !== 'department')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('code')
                                            ->required()
                                            ->maxLength(50)
                                            ->unique(),
                                        Textarea::make('description'),
                                    ]),

                                Select::make('role_id')
                                    ->relationship('role', 'name', fn (Builder $query) => $query->orderBy('name'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->label('Role')
                                    ->createOptionForm([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('guard_name')
                                            ->default('web')
                                            ->required()
                                            ->maxLength(255),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
