<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    protected Filesystem $disk;

    public function __construct(
        protected int $maxBackups = 10,
        ?string $path = null,
    ) {
        $this->disk = Storage::build([
            'driver' => 'local',
            'root' => $path ?? config('backup.path', storage_path('app/backups')),
        ]);
    }

    /**
     * Create a new database backup.
     *
     * @return string The filename of the created backup.
     */
    public function create(): string
    {
        $this->ensureDirectoryExists();

        $filename = $this->generateFilename();
        $tempPath = storage_path("app/temp_backup_{$filename}.sql");

        try {
            $this->runMysqldump($tempPath);
            $this->compressBackup($tempPath, $filename);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        $this->enforceMaxBackups();

        return $filename;
    }

    /**
     * Get all backup files sorted by newest first.
     *
     * @return Collection<int, array{filename: string, size: int, last_modified: int}>
     */
    public function getAll(): Collection
    {
        $files = collect($this->disk->files())
            ->filter(fn (string $file): bool => str_ends_with($file, '.sql.gz'))
            ->map(fn (string $file): array => [
                'filename' => $file,
                'size' => $this->disk->size($file),
                'last_modified' => $this->disk->lastModified($file),
            ])
            ->sortByDesc('last_modified')
            ->values();

        return $files;
    }

    /**
     * Get a single backup file's metadata.
     */
    public function get(string $filename): ?array
    {
        if (! $this->disk->exists($filename)) {
            return null;
        }

        return [
            'filename' => $filename,
            'size' => $this->disk->size($filename),
            'last_modified' => $this->disk->lastModified($filename),
            'path' => $this->disk->path($filename),
        ];
    }

    /**
     * Restore the database from a backup file.
     *
     * Decompresses the .sql.gz file and pipes the SQL into the mysql client.
     *
     * @throws \RuntimeException|\Symfony\Component\Process\Exception\ProcessFailedException
     */
    public function restore(string $filename): void
    {
        if (! $this->disk->exists($filename)) {
            throw new \RuntimeException("Backup file '{$filename}' not found.");
        }

        $config = $this->getConnectionConfig();
        $path = $this->disk->path($filename);

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException('Failed to read backup file.');
        }

        $sql = gzdecode($contents);

        if ($sql === false) {
            throw new \RuntimeException('Failed to decompress backup file. The file may be corrupted.');
        }

        $process = new Process(
            command: array_filter([
                $this->resolveMysqlBinary(),
                '--host=' . $config['host'],
                '--port=' . $config['port'],
                '--user=' . $config['username'],
                '--password=' . $config['password'],
                $config['database'],
            ]),
            timeout: 300,
        );

        $process->setInput($sql);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Delete a backup file.
     */
    public function delete(string $filename): bool
    {
        if (! $this->disk->exists($filename)) {
            return false;
        }

        return $this->disk->delete($filename);
    }

    /**
     * Get the full path to a backup file for download.
     */
    public function path(string $filename): ?string
    {
        if (! $this->disk->exists($filename)) {
            return null;
        }

        return $this->disk->path($filename);
    }

    /**
     * Format bytes to a human-readable size string.
     */
    public static function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Ensure the backup directory exists.
     */
    protected function ensureDirectoryExists(): void
    {
        $path = $this->disk->path('');

        if (! is_dir($path)) {
            @mkdir($path, 0755, true);
        }
    }

    /**
     * Generate a timestamped backup filename.
     */
    protected function generateFilename(): string
    {
        return 'backup-' . now()->format('Y-m-d-H-i-s') . '.sql.gz';
    }

    /**
     * Run mysqldump to export the database.
     */
    protected function runMysqldump(string $outputPath): void
    {
        $config = $this->getConnectionConfig();

        $command = array_filter([
            $this->resolveDumpBinary(),
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['username'],
            '--password=' . $config['password'],
            '--triggers',
            '--skip-routines',
            '--single-transaction',
            '--skip-lock-tables',
            $config['database'],
        ]);

        $process = new Process(
            command: $command,
            timeout: 300,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        file_put_contents($outputPath, $process->getOutput());
    }

    /**
     * Gzip compress the raw SQL dump into the final .sql.gz file.
     */
    protected function compressBackup(string $sqlPath, string $filename): void
    {
        $gzPath = $this->disk->path($filename);

        $process = new Process(
            command: ['gzip', '--best', '--stdout', $sqlPath],
            timeout: 300,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        file_put_contents($gzPath, $process->getOutput());
    }

    /**
     * Resolve the path to the mysqldump binary.
     *
     * Checks the config first, then common locations, then PATH.
     */
    protected function resolveDumpBinary(): string
    {
        $configured = config('backup.dump_binary');

        if ($configured && is_executable($configured)) {
            return $configured;
        }

        $commonPaths = [
            '/usr/bin/mysqldump',
            '/usr/local/bin/mysqldump',
            '/usr/local/mysql/bin/mysqldump',
            '/opt/homebrew/bin/mysqldump',
            '/home/linuxbrew/.linuxbrew/bin/mysqldump',
        ];

        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'mysqldump';
    }

    /**
     * Resolve the path to the mysql client binary.
     *
     * Checks the config first, then common locations, then PATH.
     */
    protected function resolveMysqlBinary(): string
    {
        $configured = config('backup.mysql_binary');

        if ($configured && is_executable($configured)) {
            return $configured;
        }

        $commonPaths = [
            '/usr/bin/mysql',
            '/usr/local/bin/mysql',
            '/usr/local/mysql/bin/mysql',
            '/opt/homebrew/bin/mysql',
            '/home/linuxbrew/.linuxbrew/bin/mysql',
        ];

        foreach ($commonPaths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }

        return 'mysql';
    }

    /**
     * Remove the oldest backups when the total exceeds the maximum.
     */
    protected function enforceMaxBackups(): void
    {
        $backups = $this->getAll();

        while ($backups->count() > $this->maxBackups) {
            $oldest = $backups->pop();

            $this->delete($oldest['filename']);
        }
    }

    /**
     * Get the database connection configuration for the default connection.
     *
     * @return array{host: string, port: string, username: string, password: string, database: string}
     */
    protected function getConnectionConfig(): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        return [
            'host' => $config['host'] ?? '127.0.0.1',
            'port' => (string) ($config['port'] ?? '3306'),
            'username' => $config['username'] ?? 'root',
            'password' => $config['password'] ?? '',
            'database' => $config['database'] ?? '',
        ];
    }
}
