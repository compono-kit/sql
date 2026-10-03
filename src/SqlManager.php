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

		$this->pdo->setAttribute( \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION );

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
		try
		{
			$result = $this->getPdo()->beginTransaction();
		}
		catch ( \PDOException $exception )
		{
			throw new TransactionRuntimeException( 'Beginning transaction failed: ' . $exception->getMessage(), 0, $exception );
		}

		if ( !$result )
		{
			throw new TransactionRuntimeException( 'Beginning transaction failed' );
		}
	}

	/**
	 * @throws TransactionLogicException
	 */
	public function commit(): void
	{
		try
		{
			$result = $this->getPdo()->commit();
		}
		catch ( \PDOException $exception )
		{
			throw new TransactionLogicException( 'Committing transaction failed: ' . $exception->getMessage(), 0, $exception );
		}

		if ( !$result )
		{
			throw new TransactionLogicException( 'Committing transaction failed' );
		}
	}

	/**
	 * @throws TransactionLogicException
	 */
	public function rollBack(): void
	{
		try
		{
			$result = $this->getPdo()->rollBack();
		}
		catch ( \PDOException $exception )
		{
			throw new TransactionLogicException( 'Transaction rollback failed: ' . $exception->getMessage(), 0, $exception );
		}

		if ( !$result )
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
		return new PreparedSqlStatement( $this->prepareQuery( $query ) );
	}

	/**
	 * @throws QueryException
	 */
	public function execute( string $query, array $params = [] ): int
	{
		$statement = $this->prepareQuery( $query );

		try
		{
			$statement->execute( $params ? : null );
		}
		catch ( \PDOException $exception )
		{
			throw new QueryException( $query, $params, $exception );
		}

		return $statement->rowCount();
	}

	public function lastInsertId(): string
	{
		return (string)$this->getPdo()->lastInsertId();
	}

	/**
	 * @throws QueryException
	 */
	public function importDump( string $filePathName ): void
	{
		if ( !is_file( $filePathName ) || !is_readable( $filePathName ) )
		{
			throw new \RuntimeException( sprintf( 'Dump file "%s" is not readable', $filePathName ) );
		}

		$dump = file_get_contents( $filePathName );

		if ( false === $dump )
		{
			throw new \RuntimeException( sprintf( 'Dump file "%s" could not be read', $filePathName ) );
		}

		try
		{
			$this->getPdo()->exec( $dump );
		}
		catch ( \PDOException $exception )
		{
			throw new QueryException( $dump, [], $exception );
		}
	}

	protected function configure(): void
	{
	}

	/**
	 * @throws QueryException
	 */
	private function prepareQuery( string $query ): \PDOStatement
	{
		try
		{
			return $this->getPdo()->prepare( $query );
		}
		catch ( \PDOException $exception )
		{
			throw new QueryException( $query, [], $exception );
		}
	}
}
