<?php

namespace DreamFactory\Core\Database;

class DatabaseManager extends \Illuminate\Database\DatabaseManager
{
    /**
     * Get all of the support drivers.
     *
     * @return array
     */
    public function supportedDrivers()
    {
        return ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];
    }
}
