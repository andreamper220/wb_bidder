<?php

namespace App\Tests\Integration;

use PDO;

trait DatabaseExtensionCheck
{
    protected function skipIfNoDatabaseDriver(): void
    {
        if (in_array('pgsql', PDO::getAvailableDrivers(), true) || in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            return;
        }

        $this->markTestSkipped('PDO pgsql/sqlite driver not available (use Docker for integration tests).');
    }
}
