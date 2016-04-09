<?php
namespace DreamFactory\Core\Database;

use DreamFactory\Core\Contracts\SchemaInterface;
use DreamFactory\Core\Database\Schema\IbmSchema;
use DreamFactory\Core\Database\Schema\MySqlSchema;
use DreamFactory\Core\Database\Schema\OracleSchema;
use DreamFactory\Core\Database\Schema\PostgresSchema;
use DreamFactory\Core\Database\Schema\SqlAnywhereSchema;
use DreamFactory\Core\Database\Schema\SqliteSchema;
use DreamFactory\Core\Database\Schema\SqlServerSchema;
use Illuminate\Database\ConnectionInterface;

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
     * @return \DreamFactory\Core\Contracts\SchemaInterface if Connection does not support reading schema for specified database driver
     * @throws \Exception if Connection does not support reading schema for specified database driver
     */
    public function getSchema(ConnectionInterface $conn)
    {
        if ($this->schemaExtension === null) {
            $driver = $conn->getDriverName();
            switch ($driver) {
                case 'ibm':
                    $this->schemaExtension = new IbmSchema($conn);
                    break;
                case 'mysql':
                    $this->schemaExtension = new MySqlSchema($conn);
                    break;
                case 'oracle':
                    $this->schemaExtension = new OracleSchema($conn);
                    break;
                case 'pgsql':
                    $this->schemaExtension = new PostgresSchema($conn);
                    break;
                case 'sqlanywhere':
                    $this->schemaExtension = new SqlAnywhereSchema($conn);
                    break;
                case 'sqlite':
                    $this->schemaExtension = new SqliteSchema($conn);
                    break;
                case 'sqlsrv':
                    $this->schemaExtension = new SqlServerSchema($conn);
                    break;
                default:
                    throw new \Exception("Driver '$driver' is not supported by this software.");
                    break;
            }
        }

        return $this->schemaExtension;
    }
}
