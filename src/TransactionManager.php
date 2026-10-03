<?php declare(strict_types=1);

namespace ComponoKit\Sql;

use ComponoKit\Sql\Exceptions\TransactionLogicException;
use ComponoKit\Sql\Interfaces\ManagesRelationalDatabases;
use ComponoKit\Sql\Interfaces\ManagesTransactions;

class TransactionManager implements ManagesTransactions
{
	private const SAVEPOINT_PREFIX = 'transaction_level_';

	private int $transactionLevel = 0;

	public function __construct( private ManagesRelationalDatabases $databaseManager )
	{
	}

	public function begin(): void
	{
		if ( 0 === $this->transactionLevel )
		{
			if ( $this->databaseManager->inTransaction() )
			{
				throw new TransactionLogicException( 'A transaction was already started outside of the transaction manager' );
			}

			$this->databaseManager->beginTransaction();
		}
		else
		{
			$this->databaseManager->execute( 'SAVEPOINT ' . $this->buildSavepointName( $this->transactionLevel ) );
		}

		$this->transactionLevel++;
	}

	public function commit(): void
	{
		$this->guardTransactionIsActive();

		try
		{
			if ( 1 === $this->transactionLevel )
			{
				$this->databaseManager->commit();
			}
			else
			{
				$this->databaseManager->execute( 'RELEASE SAVEPOINT ' . $this->buildSavepointName( $this->transactionLevel - 1 ) );
			}
		}
		finally
		{
			$this->transactionLevel--;
		}
	}

	public function rollBack(): void
	{
		$this->guardTransactionIsActive();

		try
		{
			if ( 1 === $this->transactionLevel )
			{
				$this->databaseManager->rollBack();
			}
			else
			{
				$this->databaseManager->execute( 'ROLLBACK TO SAVEPOINT ' . $this->buildSavepointName( $this->transactionLevel - 1 ) );
			}
		}
		finally
		{
			$this->transactionLevel--;
		}
	}

	public function inTransaction(): bool
	{
		return 0 < $this->transactionLevel;
	}

	public function getTransactionLevel(): int
	{
		return $this->transactionLevel;
	}

	public function transactional( callable $operation ): mixed
	{
		$this->begin();
		$startedLevel = $this->transactionLevel;

		try
		{
			$result = $operation( $this );
		}
		catch ( \Throwable $throwable )
		{
			if ( $startedLevel === $this->transactionLevel )
			{
				$this->rollBack();
			}

			throw $throwable;
		}

		$this->commit();

		return $result;
	}

	private function guardTransactionIsActive(): void
	{
		if ( 0 === $this->transactionLevel )
		{
			throw new TransactionLogicException( 'No active transaction' );
		}
	}

	private function buildSavepointName( int $level ): string
	{
		return self::SAVEPOINT_PREFIX . $level;
	}
}
