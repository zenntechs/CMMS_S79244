<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to boot a test that is not pointing at an isolated test database.
     *
     * This runs before parent::setUp(), which is the point where
     * RefreshDatabase would execute migrate:fresh.
     */
    protected function setUp(): void
    {
        $environment = (string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? ''));
        $database = (string) (getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ''));

        if ($environment !== 'testing') {
            throw new RuntimeException(
                "Refusing to run tests outside the testing environment (APP_ENV={$environment})."
            );
        }

        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against non-test database [{$database}]."
            );
        }

        parent::setUp();
    }
}
