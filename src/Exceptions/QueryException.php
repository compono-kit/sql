<?php declare(strict_types=1);

namespace ComponoKit\Sql\Exceptions;

class QueryException extends \LogicException
{
	private array  $errors             = [];

	private string $query              = '';

	private array  $preparedParameters = [];

	public function getErrors(): array
	{
		return $this->errors;
	}

	public function getQuery(): string
	{
		return $this->query;
	}

	public function getPreparedParameters(): array
	{
		return $this->preparedParameters;
	}

	public function withErrors( array $errors ): self
	{
		$this->errors = $errors;

		return $this;
	}

	public function withQuery( string $query ): self
	{
		$this->query = $query;

		return $this;
	}

	public function withPreparedParameters( array $preparedParameters ): self
	{
		$this->preparedParameters = $preparedParameters;

		return $this;
	}
}
