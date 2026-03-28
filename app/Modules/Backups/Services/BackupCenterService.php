<?php

namespace App\Modules\Backups\Services;

use App\Models\SystemBackup;
use App\Services\AuditLogService;
use App\Services\PostgresBackupService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;

class BackupCenterService
{
    public function __construct(
        private readonly PostgresBackupService $postgresBackupService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function getIndexData(array $filters): array
    {
        $filters = [
            'search' => trim((string) ($filters['search'] ?? '')),
            'status' => (string) ($filters['status'] ?? ''),
        ];

        return [
            'filters' => $filters,
            'backups' => $this->table($filters),
            'summary' => $this->summary(),
            'abilities' => [
                'create' => auth()->user()?->hasRole('super-admin') === true,
                'restore' => auth()->user()?->hasRole('super-admin') === true,
                'delete' => auth()->user()?->hasRole('super-admin') === true,
                'download' => auth()->user()?->hasRole('super-admin') === true,
            ],
        ];
    }

    public function createBackup(array $payload): SystemBackup
    {
        $backup = DB::transaction(function (): SystemBackup {
            return SystemBackup::query()->create([
                'backup_no' => $this->nextBackupNumber(),
                'backup_type' => 'database_only',
                'dump_format' => 'sql_zip',
                'status' => 'processing',
                'disk' => config('system_backup.disk', 'local'),
                'created_by_user_id' => auth()->id(),
                'started_at' => now(),
            ]);
        });

        $temporaryDirectory = $this->temporaryDirectory();
        $sqlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'database.sql';
        $metadataPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'metadata.json';
        $zipPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'backup.zip';

        try {
            $dumpMeta = $this->postgresBackupService->createSqlDump($sqlPath);

            $metadata = [
                'backup_no' => $backup->backup_no,
                'backup_type' => 'database_only',
                'dump_format' => 'sql_zip',
                'created_at' => now()->toIso8601String(),
                'created_by_user_id' => auth()->id(),
                'environment' => app()->environment(),
                'database' => $dumpMeta['database'] ?? config('database.default'),
                'driver' => $dumpMeta['driver'] ?? config('database.default'),
                'notes' => $payload['notes'] ?? null,
            ];

            file_put_contents($metadataPath, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->createZipArchive($zipPath, [
                'database.sql' => $sqlPath,
                'metadata.json' => $metadataPath,
            ]);

            $fileName = $backup->backup_no . '.zip';
            $filePath = trim(config('system_backup.path', 'backups/database'), '/') . '/' . now()->format('Y/m/d') . '/' . $fileName;
            $disk = $backup->disk;

            Storage::disk($disk)->put($filePath, file_get_contents($zipPath));

            $backup->update([
                'status' => 'ready',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size_bytes' => Storage::disk($disk)->size($filePath),
                'notes' => $payload['notes'] ?? null,
                'completed_at' => now(),
                'metadata' => $metadata,
            ]);

            $fresh = $backup->fresh();

            $this->auditLogService->log(
                'backups',
                'created',
                $fresh,
                'Backup database manual berhasil dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            return $fresh;
        } catch (Throwable $exception) {
            $backup->update([
                'status' => 'failed',
                'failure_reason' => Str::limit($exception->getMessage(), 2000),
                'completed_at' => now(),
                'notes' => $payload['notes'] ?? null,
            ]);

            $fresh = $backup->fresh();

            $this->auditLogService->log(
                'backups',
                'failed',
                $fresh,
                'Backup database gagal dibuat.',
                [],
                $fresh?->toArray() ?? [],
            );

            throw ValidationException::withMessages([
                'backup' => $exception->getMessage(),
            ]);
        } finally {
            $this->cleanupTemporaryDirectory($temporaryDirectory);
        }
    }

    public function downloadBackup(SystemBackup $backup): BinaryFileResponse
    {
        $this->ensureBackupFileExists($backup);

        $this->auditLogService->log(
            'backups',
            'downloaded',
            $backup,
            'Backup diunduh dari dashboard.',
            [],
            $backup->toArray(),
        );

        return response()->download(
            Storage::disk($backup->disk)->path($backup->file_path),
            $backup->file_name
        );
    }

    public function restoreBackup(SystemBackup $backup, array $payload): SystemBackup
    {
        $this->ensureBackupFileExists($backup);

        $temporaryDirectory = $this->temporaryDirectory();
        $zipCopyPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'backup.zip';
        $sqlPath = $temporaryDirectory . DIRECTORY_SEPARATOR . 'database.sql';
        $before = $backup->toArray();

        try {
            file_put_contents($zipCopyPath, Storage::disk($backup->disk)->get($backup->file_path));
            $this->extractZipArchive($zipCopyPath, $temporaryDirectory);

            if (! file_exists($sqlPath)) {
                throw ValidationException::withMessages([
                    'restore_reason' => 'File database.sql tidak ditemukan di dalam arsip backup.',
                ]);
            }

            $this->postgresBackupService->restoreSqlDump($sqlPath);

            $metadata = $backup->metadata ?? [];
            $metadata['last_restore_reason'] = $payload['restore_reason'];
            $metadata['last_restored_at'] = now()->toIso8601String();

            $backup->update([
                'status' => 'restored',
                'restored_at' => now(),
                'restored_by_user_id' => auth()->id(),
                'metadata' => $metadata,
            ]);

            $fresh = $backup->fresh();

            $this->auditLogService->log(
                'backups',
                'restored',
                $fresh,
                'Backup database direstore secara manual.',
                $before,
                $fresh?->toArray() ?? [],
                [
                    'restore_reason' => $payload['restore_reason'],
                ],
            );

            return $fresh;
        } finally {
            $this->cleanupTemporaryDirectory($temporaryDirectory);
        }
    }

    public function archiveBackup(SystemBackup $backup, array $payload): void
    {
        $before = $backup->toArray();
        $backup->delete();

        $this->auditLogService->log(
            'backups',
            'archived',
            $backup,
            'Backup diarsipkan dari dashboard.',
            $before,
            [],
            [
                'archive_reason' => $payload['archive_reason'] ?? null,
            ],
        );
    }

    private function table(array $filters): LengthAwarePaginator
    {
        return SystemBackup::query()
            ->with(['createdBy:id,name', 'restoredBy:id,name'])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $query->where(function (Builder $searchQuery) use ($filters): void {
                    $searchQuery
                        ->where('backup_no', 'like', '%' . $filters['search'] . '%')
                        ->orWhere('file_name', 'like', '%' . $filters['search'] . '%');
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();
    }

    private function summary(): array
    {
        $baseQuery = SystemBackup::query();

        return [
            'total' => (clone $baseQuery)->count(),
            'ready' => (clone $baseQuery)->where('status', 'ready')->count(),
            'restored' => (clone $baseQuery)->where('status', 'restored')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
        ];
    }

    private function nextBackupNumber(): string
    {
        $prefix = 'BKP-' . now()->format('Ymd') . '-';

        $lastNumber = SystemBackup::query()
            ->where('backup_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('backup_no')
            ->filter()
            ->map(function (string $number): int {
                preg_match('/(\d+)$/', $number, $matches);

                return (int) ($matches[1] ?? 0);
            })
            ->max() ?? 0;

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    private function ensureBackupFileExists(SystemBackup $backup): void
    {
        if (! $backup->file_path || ! Storage::disk($backup->disk)->exists($backup->file_path)) {
            throw ValidationException::withMessages([
                'backup' => 'File backup tidak ditemukan di storage.',
            ]);
        }
    }

    private function temporaryDirectory(): string
    {
        $directory = storage_path('app/tmp/backups/' . Str::uuid()->toString());

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory;
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function createZipArchive(string $targetPath, array $entries): void
    {
        $archive = new ZipArchive();

        if ($archive->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw ValidationException::withMessages([
                'backup' => 'Arsip backup tidak bisa dibuat.',
            ]);
        }

        foreach ($entries as $name => $sourcePath) {
            $archive->addFile($sourcePath, $name);
        }

        $archive->close();
    }

    private function extractZipArchive(string $sourcePath, string $targetDirectory): void
    {
        $archive = new ZipArchive();

        if ($archive->open($sourcePath) !== true) {
            throw ValidationException::withMessages([
                'restore_reason' => 'Arsip backup tidak bisa dibuka.',
            ]);
        }

        $archive->extractTo($targetDirectory);
        $archive->close();
    }

    private function cleanupTemporaryDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        collect(scandir($directory) ?: [])
            ->reject(fn (string $item): bool => in_array($item, ['.', '..'], true))
            ->each(function (string $item) use ($directory): void {
                $path = $directory . DIRECTORY_SEPARATOR . $item;

                if (is_dir($path)) {
                    $this->cleanupTemporaryDirectory($path);
                    return;
                }

                @unlink($path);
            });

        @rmdir($directory);
    }
}
