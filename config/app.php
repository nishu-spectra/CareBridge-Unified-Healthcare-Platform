<?php
declare(strict_types=1);

/*
 * Keep environment-specific settings here for local development.
 * For production, load secrets from environment variables and keep config
 * outside the public document root whenever your hosting setup permits.
 */
return [
    'app_name' => 'CareBridge',
    'app_env' => getenv('APP_ENV') ?: 'local',
    'base_url' => getenv('APP_BASE_URL') !== false
        ? rtrim((string)getenv('APP_BASE_URL'), '/')
        : '/unified_healthcare_platform/public',
    'session_name' => 'carebridge_session',
    'mail_from' => getenv('MAIL_FROM') ?: 'no-reply@carebridge.local',
    'mail_from_name' => getenv('MAIL_FROM_NAME') ?: 'CareBridge',
    'database' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'carebridge',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') !== false ? (string)getenv('DB_PASSWORD') : '',
        'charset' => 'utf8mb4',
    ],
];
