<?php declare(strict_types=1);

namespace ComponoKit\Sql\Exceptions;

class QueryException extends \RuntimeException
{
	public function __construct( private string $query, private array $preparedParameters, \PDOException $previous )
	{
		parent::__construct( $previous->getMessage(), 0, $previous );
	}

	public function getQuery(): string
	{
		return $this->query;
	}

	public function getPreparedParameters(): array
	{
		return $this->preparedParameters;
	}

	public function getDriverErrorCode(): ?int
	{
		$driverErrorCode = $this->getPrevious()->errorInfo[1] ?? null;

		return null === $driverErrorCode ? null : (int)$driverErrorCode;
	}
}
