<?php

/*
|--------------------------------------------------------------------------
| Test bootstrap and database safety gate
|--------------------------------------------------------------------------
|
| The application container receives its runtime values from env_file (.env).
| PHPUnit environment variables do not reliably override variables that
| already exist in the container, so the testing values are forced here
| before Laravel loads its immutable environment repository.
|
| The gate below refuses to start the suite when the testing database is not
| clearly isolated from the development database. This prevents a repeat of
| the Phase 1 incident where a mis-targeted run executed migrate:fresh
| against the development database.
|
*/

if (defined('CMMS_TEST_BOOTSTRAP_LOADED')) {
    return;
}

define('CMMS_TEST_BOOTSTRAP_LOADED', true);

$configCachePath = __DIR__.'/../bootstrap/cache/config.php';

if (is_file($configCachePath)) {
    fwrite(
        STDERR,
        "Refusing to run tests: configuration is cached at bootstrap/cache/config.php.\n".
        "A cached configuration can ignore the test database override.\n".
        "Run `php artisan config:clear` and retry.\n"
    );

    exit(1);
}

$applicationDatabase = '';

$environmentFile = __DIR__.'/../.env';

if (is_file($environmentFile)) {
    foreach (file($environmentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^DB_DATABASE=(.*)$/', trim($line), $matches) === 1) {
            $applicationDatabase = trim($matches[1], " \t\n\r\0\x0B\"'");
            break;
        }
    }
}

$testDatabase = (string) (getenv('DB_TEST_DATABASE') ?: ($_ENV['DB_TEST_DATABASE'] ?? 'cmms_test'));

if ($testDatabase === '' || ! str_ends_with($testDatabase, '_test')) {
    fwrite(
        STDERR,
        "Refusing to run tests: DB_TEST_DATABASE must end with '_test' (got [{$testDatabase}]).\n"
    );

    exit(1);
}

if ($testDatabase === $applicationDatabase) {
    fwrite(
        STDERR,
        "Refusing to run tests: DB_TEST_DATABASE [{$testDatabase}] must differ from DB_DATABASE.\n"
    );

    exit(1);
}

$testingEnvironment = [
    'APP_ENV' => 'testing',
    'BCRYPT_ROUNDS' => '4',
    'CACHE_STORE' => 'array',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => (string) (getenv('DB_HOST') ?: 'db'),
    'DB_PORT' => (string) (getenv('DB_PORT') ?: '5432'),
    'DB_DATABASE' => $testDatabase,
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($testingEnvironment as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';
