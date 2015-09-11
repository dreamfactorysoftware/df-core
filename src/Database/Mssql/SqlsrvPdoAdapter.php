<?php
namespace DreamFactory\Core\Database\Mssql;

/**
 * This is an extension of default PDO class for MSSQL SQLSRV driver only.
 * It provides workaround of the improperly implemented functionalities of PDO SQLSRV driver.
 */
class SqlsrvPdoAdapter extends \PDO
{
    /**
     * Returns last inserted ID value.
     * SQLSRV driver supports PDO::lastInsertId() with one peculiarity: when $sequence's value is null or empty
     * string it returns empty string. But when parameter is not specified at all it's working as expected
     * and returns actual last inserted ID (like other PDO drivers).
     *
     * @param string|null the sequence name. Defaults to null.
     *
     * @return integer last inserted ID value.
     */
    public function lastInsertId($sequence = null)
    {
        if (!$sequence) {
            return parent::lastInsertId();
        }

        return parent::lastInsertId($sequence);
    }
}
