<?php

namespace App\Filament\Resources\ApprovalFlows\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                            ->schema([
                                Select::make('role_id')
                                    ->relationship('role', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->label('Role'),
                            ]),
                    ]),
            ]);
    }
}
