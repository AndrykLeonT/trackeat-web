<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        $guard ??= $this->app['auth']->getDefaultDriver();
        if ($this->app['auth']->guard($guard)->id() !== $user->getAuthIdentifier()) {
            // Cada actor de pruebas representa un navegador distinto.
            $this->app['session']->forget('password_hash_'.$guard);
        }

        return parent::actingAs($user, $guard);
    }

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
