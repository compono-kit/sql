<?php declare(strict_types=1);

namespace ComponoKit\Sql;

use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Exceptions\TransactionRuntimeException;
use ComponoKit\Sql\Interfaces\RepresentsPreparedStatement;

class MySqlManager extends SqlManager
{
	private const CONNECTION_LOST_ERROR_CODES = [ 2006, 2013 ];

	private bool $transactionStarted = false;

	public function beginTransaction(): void
	{
		$this->runWithReconnect(
			function (): void
			{
				parent::beginTransaction();
			}
		);

		$this->transactionStarted = true;
	}

	public function commit(): void
	{
		try
		{
			parent::commit();
		}
		finally
		{
			$this->transactionStarted = false;
		}
	}

	public function rollBack(): void
	{
		try
		{
			parent::rollBack();
		}
		finally
		{
			$this->transactionStarted = false;
		}
	}

	public function prepare( string $query ): RepresentsPreparedStatement
	{
		return $this->runWithReconnect(
			function () use ( $query ): RepresentsPreparedStatement
			{
				return parent::prepare( $query );
			}
		);
	}

	public function execute( string $query, array $params = [] ): int
	{
		return $this->runWithReconnect(
			function () use ( $query, $params ): int
			{
				return parent::execute( $query, $params );
			}
		);
	}

	/**
	 * @template T
	 *
	 * @param callable(): T $operation
	 *
	 * @return T
	 * @throws QueryException
	 * @throws TransactionRuntimeException
	 */
	private function runWithReconnect( callable $operation ): mixed
	{
		try
		{
			return $operation();
		}
		catch ( QueryException|TransactionRuntimeException $exception )
		{
			if ( !$this->isConnectionLost( $exception ) )
			{
				throw $exception;
			}

			if ( $this->transactionStarted )
			{
				$this->transactionStarted = false;
				$this->close();

				throw new TransactionRuntimeException( 'Connection lost during transaction', 0, $exception );
			}

			$this->connect();

			return $operation();
		}
	}

	private function isConnectionLost( \Throwable $exception ): bool
	{
		if ( $exception instanceof QueryException )
		{
			return in_array( $exception->getDriverErrorCode(), self::CONNECTION_LOST_ERROR_CODES, true );
		}

		$previous = $exception->getPrevious();

		if ( $previous instanceof \PDOException )
		{
			return in_array( (int)($previous->errorInfo[1] ?? 0), self::CONNECTION_LOST_ERROR_CODES, true );
		}

		return false;
	}
}
