<?php
namespace DreamFactory\Core\Database;

/**
 * Transaction represents a DB transaction.
 *
 * It is usually created by calling {@link Connection::beginTransaction}.
 *
 * The following code is a common scenario of using transactions:
 * <pre>
 * $transaction=$connection->beginTransaction();
 * try
 * {
 *    $connection->createCommand($sql1)->execute();
 *    $connection->createCommand($sql2)->execute();
 *    //.... other SQL executions
 *    $transaction->commit();
 * }
 * catch(Exception $e)
 * {
 *    $transaction->rollback();
 * }
 * </pre>
 *
 * @property Connection $connection The DB connection for this transaction.
 * @property boolean    $active     Whether this transaction is active.
 */
class Transaction
{
    private $connection = null;
    private $active;

    /**
     * Constructor.
     *
     * @param Connection $connection the connection associated with this transaction
     *
     * @see CDbConnection::beginTransaction
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
        $this->active = true;
    }

    /**
     * Commits a transaction.
     *
     * @throws \Exception if the transaction or the DB connection is not active.
     */
    public function commit()
    {
        if ($this->active && $this->connection->getActive()) {
            $this->connection->getPdoInstance()->commit();
            $this->active = false;
        } else {
            throw new \Exception('Transaction is inactive and cannot perform commit or roll back operations.');
        }
    }

    /**
     * Rolls back a transaction.
     *
     * @throws \Exception if the transaction or the DB connection is not active.
     */
    public function rollback()
    {
        if ($this->active && $this->connection->getActive()) {
            $this->connection->getPdoInstance()->rollBack();
            $this->active = false;
        } else {
            throw new \Exception('Transaction is inactive and cannot perform commit or roll back operations.');
        }
    }

    /**
     * @return Connection the DB connection for this transaction
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return boolean whether this transaction is active
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @param boolean $value whether this transaction is active
     */
    protected function setActive($value)
    {
        $this->active = $value;
    }
}
