<?php
namespace DreamFactory\Core\Database\Ibmdb2;

/**
 * Connection represents a connection to a IBM DB2 database.
 */
class Connection extends \DreamFactory\Core\Database\Connection
{

    protected function initConnection($pdo)
    {
        parent::initConnection($pdo);
        $this->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_LOWER);
        $this->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);
    }

    public function getPdoType($type)
    {
        if ($type == 'NULL') {
            return \PDO::PARAM_STR;
        } else {
            return parent::getPdoType($type);
        }
    }

    /**
     * @var string Custom PDO wrapper class.
     * @since 1.1.8
     */
    public $pdoClass = 'PdoAdapter';

}