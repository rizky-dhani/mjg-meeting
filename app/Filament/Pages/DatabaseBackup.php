<?php

namespace App\Filament\Pages;

use App\Services\DatabaseBackupService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use UnitEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class DatabaseBackup extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Database Backup';

    protected static ?string $slug = 'database-backup';

    protected static ?string $navigationLabel = 'Database Backup';

    protected static string | UnitEnum | null $navigationGroup = 'System Management';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): Collection => app(DatabaseBackupService::class)->getAll())
            ->columns([
                TextColumn::make('filename')
                    ->label('Filename')
                    ->searchable(),
                TextColumn::make('size')
                    ->label('Size')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => DatabaseBackupService::formatSize($state)),
                TextColumn::make('last_modified')
                    ->label('Created At')
                    ->sortable()
                    ->formatStateUsing(fn (int $state): string => Carbon::createFromTimestamp($state)->format('Y-m-d H:i:s')),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (array $record): string => route('backup.download', ['filename' => $record['filename']])),
                Action::make('restore')
                    ->label('Restore')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Restore Database Backup')
                    ->modalDescription('This will OVERWRITE the current database with the selected backup. This action cannot be undone.')
                    ->modalSubmitActionLabel('Restore Backup')
                    ->action(function (array $record) {
                        try {
                            $service = app(DatabaseBackupService::class);
                            $service->restore($record['filename']);

                            $this->flushCachedTableRecords();

                            Notification::make()
                                ->title('Database restored successfully')
                                ->body("Restored from: {$record['filename']}")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Restore failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (array $record) {
                        $service = app(DatabaseBackupService::class);

                        if (! $service->delete($record['filename'])) {
                            Notification::make()
                                ->title('Backup not found')
                                ->danger()
                                ->send();

                            return;
                        }

                        $this->flushCachedTableRecords();

                        Notification::make()
                            ->title('Backup deleted')
                            ->success()
                            ->send();
                    }),
            ])
            ->paginated(false)
            ->defaultSort('last_modified', 'desc');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload Backup')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('file')
                        ->label('Backup file (.sql.gz)')
                        ->acceptedFileTypes([
                            'application/gzip',
                            'application/x-gzip',
                            'application/x-gzip-compressed',
                        ])
                        ->maxSize(100 * 1024)
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var TemporaryUploadedFile $file */
                    $file = $data['file'];

                    $originalName = $file->getClientOriginalName();

                    if (! str_ends_with($originalName, '.sql.gz')) {
                        Notification::make()
                            ->title('Invalid file format')
                            ->body('Only .sql.gz files are accepted.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $backupPath = config('backup.path', storage_path('app/backups'));
                    $destination = $backupPath . '/' . $originalName;

                    if (file_exists($destination)) {
                        $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
                        $destination = $backupPath . '/' . $nameWithoutExt . '-' . now()->timestamp . '.sql.gz';
                    }

                    copy($file->getRealPath(), $destination);

                    $this->flushCachedTableRecords();

                    Notification::make()
                        ->title('Backup uploaded successfully')
                        ->body(basename($destination))
                        ->success()
                        ->send();
                }),
            Action::make('create')
                ->label('Create Backup')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->action(function () {
                    try {
                        $service = app(DatabaseBackupService::class);
                        $filename = $service->create();
                        $this->flushCachedTableRecords();

                        Notification::make()
                            ->title('Backup created successfully')
                            ->body($filename)
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Backup failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
