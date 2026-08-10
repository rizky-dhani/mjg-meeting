<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exports\UserExport;
use App\Filament\Resources\Users\UserResource;
use App\Imports\UserImport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\ListRecords;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => Excel::download(new UserExport, 'users_'.now()->format('Y_m_d_His').'.xlsx')),
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
                    Excel::import(new UserImport, $file->getRealPath());
                }),
            CreateAction::make()
                ->modal(),
        ];
    }
}
