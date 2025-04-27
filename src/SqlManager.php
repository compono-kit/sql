<?php declare(strict_types=1);

namespace ComponoKit\Sql;

use ComponoKit\Sql\Components\PreparedSqlStatement;
use ComponoKit\Sql\Configs\Interfaces\ConfiguresSqlManager;
use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Exceptions\TransactionLogicException;
use ComponoKit\Sql\Exceptions\TransactionRuntimeException;
use ComponoKit\Sql\Interfaces\ManagesRelationalDatabases;
use ComponoKit\Sql\Interfaces\RepresentsPreparedStatement;

class SqlManager implements ManagesRelationalDatabases
{
	private ConfiguresSqlManager $config;

	private ?\PDO                $pdo = null;

	public function __construct( ConfiguresSqlManager $config )
	{
		$this->config = $config;
	}

	public function getPdo(): \PDO
	{
		if ( !$this->isConnected() )
		{
			$this->connect();
		}

		return $this->pdo;
	}

	public function isConnected(): bool
	{
		return null !== $this->pdo;
	}

	final public function connect(): void
	{
		$this->pdo = new \PDO(
			$this->config->getDsn(),
			$this->config->getUser(),
			$this->config->getPassword(),
			$this->config->getDriverOptions()
		);

		$this->configure();
	}

	final public function close(): void
	{
		$this->pdo = null;
	}

	/**
	 * @throws TransactionRuntimeException
	 */
	public function beginTransaction(): void
	{
		if ( !$this->getPdo()->beginTransaction() )
		{
			throw new TransactionRuntimeException( 'Beginning transaction failed' );
		}
	}

	/**
	 * @throws TransactionLogicException
	 */
	public function commit(): void
	{
		if ( !$this->getPdo()->commit() )
		{
			throw new TransactionLogicException( 'Committing transaction failed' );
		}
	}

	/**
	 * @throws TransactionLogicException
	 */
	public function rollBack(): void
	{
		if ( !$this->getPdo()->rollBack() )
		{
			throw new TransactionLogicException( 'Transaction rollback failed' );
		}
	}

	public function inTransaction(): bool
	{
		return $this->getPdo()->inTransaction();
	}

	/**
	 * @throws QueryException
	 */
	public function prepare( string $query ): RepresentsPreparedStatement
	{
		try
		{
			$statement = @$this->getPdo()->prepare( $query );

			if ( false === $statement || $statement->errorCode() > 0 )
			{
				throw (new QueryException( $statement->errorInfo()[2] ))->withErrors( $statement->errorInfo() )
				                                                        ->withQuery( $query );
			}

			return new PreparedSqlStatement( $statement );
		}
		catch ( \PDOException )
		{
			throw (new QueryException( $this->getPdo()->errorInfo()[2] ))->withErrors( $this->getPdo()->errorInfo() )
			                                                             ->withQuery( $query );
		}
	}

	public function importDump( string $filePathName ): void
	{
		$this->getPdo()->exec( file_get_contents( $filePathName ) );
	}

	protected function configure(): void
	{
		/** Override if needed */
	}
}
