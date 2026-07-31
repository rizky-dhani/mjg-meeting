<?php

namespace App\Filament\Resources\Divisions\Pages;

use App\Exports\DivisionExport;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Imports\DivisionImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ListDivisions extends ListRecords
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(new DivisionExport, 'divisions.xlsx')),
            Action::make('import')
                ->label('Import')
                ->color('warning')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var TemporaryUploadedFile $file */
                    $file = $data['file'];
                    Excel::import(new DivisionImport, $file->getRealPath());
                }),
            CreateAction::make()
                ->label('New Division')
                ->modal(),
        ];
    }
}
