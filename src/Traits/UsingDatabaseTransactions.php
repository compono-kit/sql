<?php declare(strict_types=1);

namespace ComponoKit\Sql\Traits;

trait UsingDatabaseTransactions
{
	public function beginTransaction(): void
	{
		$this->dbManager->beginTransaction();
	}

	public function commit(): void
	{
		$this->dbManager->commit();
	}

	public function rollBack(): void
	{
		$this->dbManager->rollBack();
	}

	public function open(): void
	{
		$this->dbManager->connect();
	}

	public function close(): void
	{
		$this->dbManager->close();
	}

	public function inTransaction(): bool
	{
		return $this->dbManager->inTransaction();
	}
}
