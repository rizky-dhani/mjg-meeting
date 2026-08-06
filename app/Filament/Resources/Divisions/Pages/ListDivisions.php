<?php

namespace App\Filament\Resources\Divisions\Pages;

use App\Exports\DivisionExport;
use App\Filament\Resources\Divisions\DivisionResource;
use App\Imports\DivisionImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

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
                ->action(fn () => Excel::download(new DivisionExport, 'divisions_' . now()->format('Y_m_d_His') . '.xlsx')),
            Action::make('import')
                ->label('Import')
                ->color('warning')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('file')
                        ->storeFiles(false)
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'text/csv',
                            'text/x-csv',
                            'application/csv',
                            'application/x-csv',
                            'text/comma-separated-values',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
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
