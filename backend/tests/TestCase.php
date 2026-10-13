<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        $database = $connection->getDatabaseName();
        if ($connection->getDriverName() !== 'pgsql' || ! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new \RuntimeException('Las pruebas requieren una base PostgreSQL dedicada cuyo nombre termine en _test.');
        }

        return $app;
    }
}
