<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_test_suite_uses_isolated_postgresql_database(): void
    {
        $connection = DB::connection();

        $this->assertSame('pgsql', $connection->getDriverName());
        $this->assertSame('cmms_test', $connection->getDatabaseName());
        $this->assertStringEndsWith('_test', $connection->getDatabaseName());
    }

    public function test_application_runs_on_postgresql_18(): void
    {
        $version = DB::connection()->selectOne('show server_version')->server_version;

        $this->assertStringStartsWith('18.', $version);
    }
}
