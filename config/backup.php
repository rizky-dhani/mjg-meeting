<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Number of Backups
    |--------------------------------------------------------------------------
    |
    | When a new backup is created and this limit is exceeded, the oldest
    | backup files are automatically removed to stay within the limit.
    |
    */

    'max_backups' => env('BACKUP_MAX_BACKUPS', 10),

    /*
    |--------------------------------------------------------------------------
    | Backup Storage Path
    |--------------------------------------------------------------------------
    |
    | The directory where database backup files will be stored. Backups
    | are stored as gzip-compressed SQL files.
    |
    */

    'path' => env('BACKUP_PATH', storage_path('app/backups')),

    /*
    |--------------------------------------------------------------------------
    | mysqldump Binary Path
    |--------------------------------------------------------------------------
    |
    | The path to the mysqldump binary. If not set, the service will try
    | to detect it automatically from common locations. You can set this
    | in your .env file if mysqldump is not in the default PATH.
    |
    | Example: BACKUP_DUMP_BINARY=/usr/local/bin/mysqldump
    |
    */

    'dump_binary' => env('BACKUP_DUMP_BINARY'),

    /*
    |--------------------------------------------------------------------------
    | mysql Client Binary Path
    |--------------------------------------------------------------------------
    |
    | The path to the mysql client binary used for restoring backups. If not
    | set, the service will try to detect it automatically from common
    | locations. You can set this in your .env file if needed.
    |
    | Example: BACKUP_MYSQL_BINARY=/usr/local/bin/mysql
    |
    */

    'mysql_binary' => env('BACKUP_MYSQL_BINARY'),

];
