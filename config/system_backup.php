<?php

return [
    'disk' => env('SYSTEM_BACKUP_DISK', 'local'),
    'path' => env('SYSTEM_BACKUP_PATH', 'backups/database'),
    'pg_dump_binary' => env('POSTGRES_DUMP_BINARY', 'pg_dump'),
    'psql_binary' => env('POSTGRES_RESTORE_BINARY', 'psql'),
    'timeout_seconds' => (int) env('SYSTEM_BACKUP_TIMEOUT', 300),
];
