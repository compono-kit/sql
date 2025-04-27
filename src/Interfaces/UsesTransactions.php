<?php declare(strict_types=1);

namespace ComponoKit\Sql\Interfaces;

interface UsesTransactions
{
	public function beginTransaction(): void;

	public function commit(): void;

	public function rollBack(): void;

	public function inTransaction(): bool;
}
