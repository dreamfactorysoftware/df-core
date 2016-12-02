<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\SchemaInterface;
use Illuminate\Database\ConnectionInterface;
use DbSchemaExtensions;

/**
 * ConnectionExtension represents a connection to a database with DreamFactory extensions.
 *
 */
trait ConnectionExtension
{
    /**
     * @var SchemaInterface
     */
    protected $schemaExtension;

    /**
     * Returns the database schema for the current connection
     *
     * @param \Illuminate\Database\Connection|\Illuminate\Database\ConnectionInterface $conn
     *
     * @return SchemaInterface
     * @throws \Exception if Connection does not support reading schema for specified database driver
     */
    public function getSchemaExtension(ConnectionInterface $conn)
    {
        if ($this->schemaExtension === null) {
            $driver = $conn->getDriverName();
            if (null === $this->schemaExtension = DbSchemaExtensions::getSchemaExtension($driver, $conn)) {
                throw new \Exception("Driver '$driver' is not supported by this software.");
            }
        }

        return $this->schemaExtension;
    }
}
