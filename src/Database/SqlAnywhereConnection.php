<?php

namespace DreamFactory\Core\Database;

use Doctrine\DBAL\Driver\PDOSqlsrv\Driver as DoctrineDriver;
use DreamFactory\Core\Database\Query\Grammars\SqlAnywhereGrammar as QueryGrammar;
use DreamFactory\Core\Database\Query\Processors\SqlAnywhereProcessor;
use DreamFactory\Core\Database\Schema\Grammars\SqlAnywhereGrammar as SchemaGrammar;
use Illuminate\Database\Connection;

class SqlAnywhereConnection extends Connection
{
	/**
	 * Execute a Closure within a transaction.
	 *
	 * @param  \Closure  $callback
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	public function transaction(\Closure $callback)
	{
		if ($this->getDriverName() == 'pdo_bdlib')
		{
			return parent::transaction($callback);
		}

		$this->pdo->exec('BEGIN TRAN');

		// We'll simply execute the given callback within a try / catch block
		// and if we catch any exception we can rollback the transaction
		// so that none of the changes are persisted to the database.
		try
		{
			$result = $callback($this);

			$this->pdo->exec('COMMIT TRAN');
		}

		// If we catch an exception, we will roll back so nothing gets messed
		// up in the database. Then we'll re-throw the exception so it can
		// be handled how the developer sees fit for their applications.
		catch (\Exception $e)
		{
			$this->pdo->exec('ROLLBACK TRAN');

			throw $e;
		}

		return $result;
	}
    
    /**
     * Get the default query grammar instance.
     *
     * @return QueryGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new QueryGrammar);
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return SchemaGrammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new SchemaGrammar);
    }

    /**
     * Get the default post processor instance.
     *
     * @return SqlAnywhereProcessor
     */
    protected function getDefaultPostProcessor()
    {
        return new SqlAnywhereProcessor;
    }

    /**
     * Get the Doctrine DBAL driver.
     *
     * @return \Doctrine\DBAL\Driver\PDOSqlsrv\Driver
     */
    protected function getDoctrineDriver()
    {
        return new DoctrineDriver;
    }
}
