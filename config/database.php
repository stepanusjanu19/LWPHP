<?php

/**
 * Database configuration — supports all major RDBMS via Doctrine DBAL.
 *
 * Switch the driver by setting DB_CONNECTION in .env:
 *   sqlite    → pdo_sqlite   (zero-config default)
 *   mysql     → pdo_mysql
 *   pgsql     → pdo_pgsql
 *   sqlserver → pdo_sqlsrv
 *
 * The Doctrine EntityManager is wired in config/di/definitions.php.
 */
return [

    /*
     |-----------------------------------------------------------
     | Default Connection — change DB_CONNECTION in .env
     |-----------------------------------------------------------
     */
    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
     |-----------------------------------------------------------
     | Entity / Migration Paths
     |-----------------------------------------------------------
     */
    'entity_path' => base_path('src/Entity'),
    'migrations_path' => base_path('database/migrations'),

    /*
     |-----------------------------------------------------------
     | Connections
     |-----------------------------------------------------------
     */
    'connections' => [

        /* ── SQLite (zero-config default) ── */
        'sqlite' => [
            'driver' => 'pdo_sqlite',
            'path' => env('DB_DATABASE', storage_path('database.sqlite')),
        ],

        /* ── MySQL / MariaDB ── */
        'mysql' => [
            'driver' => 'pdo_mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 3306),
            'dbname' => env('DB_DATABASE', 'lwphp'),
            'user' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'driverOptions' => defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? [
                \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_unicode_ci'",
            ] : [],
        ],

        /* ── PostgreSQL ── */
        'pgsql' => [
            'driver' => 'pdo_pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int)env('DB_PORT', 5432),
            'dbname' => env('DB_DATABASE', 'lwphp'),
            'user' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
        ],

        /* ── Microsoft SQL Server ── */
        'sqlserver' => [
            'driver' => 'pdo_sqlsrv',
            'host' => env('DB_HOST', 'localhost'),
            'port' => (int)env('DB_PORT', 1433),
            'dbname' => env('DB_DATABASE', 'lwphp'),
            'user' => env('DB_USERNAME', 'sa'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];