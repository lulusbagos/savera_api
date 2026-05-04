<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSafeTestingDatabase();
    }

    private function assertSafeTestingDatabase(): void
    {
        $appEnv = (string) app()->environment();
        $connection = (string) config('database.default', '');
        $database = (string) config("database.connections.{$connection}.database", '');

        if ($appEnv !== 'testing') {
            throw new RuntimeException("Refusing to run tests outside testing environment. Current env: {$appEnv}");
        }

        if ($connection === 'sqlite') {
            return;
        }

        if (stripos($database, 'test') === false) {
            throw new RuntimeException(
                "Unsafe testing database detected: connection={$connection}, database={$database}. ".
                "Use a dedicated test database (name should include 'test')."
            );
        }
    }
}
