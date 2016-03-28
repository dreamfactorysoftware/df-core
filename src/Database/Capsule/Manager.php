<?php

namespace DreamFactory\Core\Database\Capsule;

use DreamFactory\Core\Database\DatabaseManager;
use DreamFactory\Core\Database\Connectors\ConnectionFactory;

class Manager extends \Illuminate\Database\Capsule\Manager
{
    /**
     * Build the database manager instance.
     *
     * @return void
     */
    protected function setupManager()
    {
        $factory = new ConnectionFactory($this->container);

        $this->manager = new DatabaseManager($this->container, $factory);
    }
}
