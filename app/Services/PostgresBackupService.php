<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class PostgresBackupService
{
    public function createSqlDump(string $targetPath): array
    {
        if (app()->environment('testing')) {
            file_put_contents($targetPath, $this->fakeSqlDump());

            return [
                'driver' => 'testing-fake',
                'database' => config('database.default'),
            ];
        }

        $connection = $this->pgsqlConnection();
        $process = new Process([
            config('system_backup.pg_dump_binary', 'pg_dump'),
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--encoding=UTF8',
            '--host=' . $connection['host'],
            '--port=' . $connection['port'],
            '--username=' . $connection['username'],
            '--dbname=' . $connection['database'],
            '--file=' . $targetPath,
        ], base_path(), $this->processEnvironment($connection));

        $process->setTimeout((int) config('system_backup.timeout_seconds', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Proses pg_dump gagal.');
        }

        return [
            'driver' => 'pgsql',
            'database' => $connection['database'],
        ];
    }

    public function restoreSqlDump(string $sourcePath): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $connectionName = config('database.default');
        $connection = $this->pgsqlConnection();

        DB::disconnect($connectionName);
        DB::purge($connectionName);

        Artisan::call('down', [
            '--render' => 'errors::503',
        ]);

        try {
            $process = new Process([
                config('system_backup.psql_binary', 'psql'),
                '--set',
                'ON_ERROR_STOP=on',
                '--single-transaction',
                '--host=' . $connection['host'],
                '--port=' . $connection['port'],
                '--username=' . $connection['username'],
                '--dbname=' . $connection['database'],
                '--file=' . $sourcePath,
            ], base_path(), $this->processEnvironment($connection));

            $process->setTimeout((int) config('system_backup.timeout_seconds', 300));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Proses restore database gagal.');
            }
        } finally {
            Artisan::call('up');
            DB::reconnect($connectionName);
        }
    }

    private function pgsqlConnection(): array
    {
        $connectionName = config('database.default');
        $connection = config('database.connections.' . $connectionName);

        if (($connection['driver'] ?? null) !== 'pgsql') {
            throw new RuntimeException('Backup & restore saat ini hanya mendukung PostgreSQL.');
        }

        return [
            'host' => (string) ($connection['host'] ?? '127.0.0.1'),
            'port' => (string) ($connection['port'] ?? '5432'),
            'database' => (string) ($connection['database'] ?? ''),
            'username' => (string) ($connection['username'] ?? ''),
            'password' => (string) ($connection['password'] ?? ''),
        ];
    }

    private function processEnvironment(array $connection): array
    {
        return [
            'PGPASSWORD' => $connection['password'],
        ];
    }

    private function fakeSqlDump(): string
    {
        return implode(PHP_EOL, [
            '-- CSI Clinic HIS testing backup',
            '-- Generated at ' . now()->toDateTimeString(),
            'SELECT 1;',
            '',
        ]);
    }
}
