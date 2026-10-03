<?php declare(strict_types=1);

namespace ComponoKit\Sql\Interfaces;

use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Exceptions\TransactionLogicException;
use ComponoKit\Sql\Exceptions\TransactionRuntimeException;

interface ManagesTransactions
{
	/**
	 * @throws TransactionLogicException
	 * @throws TransactionRuntimeException
	 * @throws QueryException
	 */
	public function begin(): void;

	/**
	 * @throws TransactionLogicException
	 * @throws QueryException
	 */
	public function commit(): void;

	/**
	 * @throws TransactionLogicException
	 * @throws QueryException
	 */
	public function rollBack(): void;

	public function inTransaction(): bool;

	public function getTransactionLevel(): int;

	/**
	 * @template T
	 *
	 * @param callable(ManagesTransactions): T $operation
	 *
	 * @return T
	 */
	public function transactional( callable $operation ): mixed;
}
