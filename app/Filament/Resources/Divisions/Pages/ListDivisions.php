<?php

namespace App\Filament\Resources\Divisions\Pages;

use App\Exports\DivisionExport;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Imports\DivisionImport;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;

use function Livewire\livewire;

class ListDivisions extends ListRecords
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->modal(),
            ExportAction::make()
                ->exporter(DivisionExport::class),
            ImportAction::make()
                ->importer(DivisionImport::class),
        ];
    }
}
