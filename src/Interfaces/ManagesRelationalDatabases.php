<?php declare(strict_types=1);

namespace ComponoKit\Sql\Interfaces;

use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Exceptions\TransactionLogicException;
use ComponoKit\Sql\Exceptions\TransactionRuntimeException;

interface ManagesRelationalDatabases
{
	public function isConnected(): bool;

	public function connect(): void;

	public function close(): void;

	/**
	 * @throws TransactionRuntimeException
	 */
	public function beginTransaction(): void;

	/**
	 * @throws TransactionLogicException
	 */
	public function commit(): void;

	/**
	 * @throws TransactionLogicException
	 */
	public function rollBack(): void;

	public function inTransaction(): bool;

	/**
	 * @throws QueryException
	 */
	public function prepare( string $query ): RepresentsPreparedStatement;

	public function execute( string $query, array $params ): void;

	/**
	 * @throws QueryException
	 */
	public function importDump( string $filePathName ): void;
}
