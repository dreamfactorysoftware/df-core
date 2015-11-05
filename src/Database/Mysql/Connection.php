<?php
namespace DreamFactory\Core\Database\Mysql;

/**
 * Connection represents a connection to a MySQL database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{
    public static function getDriverLabel()
    {
        return 'MySQL';
    }

    public static function getSampleDsn()
    {
        // http://php.net/manual/en/ref.pdo-mysql.connection.php
        return 'mysql:host=localhost;port=3306;dbname=db';
    }

    public function getSchema()
    {
        if ($this->schema !== null) {
            return $this->schema;
        }

        return new Schema($this);
    }
}
