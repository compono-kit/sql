<?php declare(strict_types=1);

namespace ComponoKit\Sql\Components;

use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Interfaces\RepresentsPreparedStatement;

class PreparedSqlStatement implements RepresentsPreparedStatement
{
	public function __construct( private \PDOStatement $pdoStatement )
	{
	}

	/**
	 * @throws QueryException
	 */
	public function fetchValue( array $params = [] ): ?string
	{
		$this->execute( $params );
		$result = $this->fetchFromStatement( \PDO::FETCH_COLUMN );

		if ( false === $result )
		{
			return null;
		}

		return $this->castToNullableString( $result );
	}

	/**
	 * @param array $params
	 *
	 * @return \Iterator<int,?string>
	 * @throws QueryException
	 */
	public function fetchValues( array $params = [] ): \Iterator
	{
		$this->execute( $params );

		while ( false !== ($value = $this->fetchFromStatement( \PDO::FETCH_COLUMN )) )
		{
			yield $this->castToNullableString( $value );
		}
	}

	/**
	 * @throws QueryException
	 */
	public function fetchEntity( string $className, array $params = [] ): ?object
	{
		$this->execute( $params );
		$entity = $this->fetchObjectFromStatement( $className );

		if ( false === $entity )
		{
			return null;
		}

		return $entity;
	}

	/**
	 * @param string $className
	 * @param array  $params
	 *
	 * @return \Iterator<int, object>
	 * @throws QueryException
	 */
	public function fetchEntities( string $className, array $params = [] ): \Iterator
	{
		$this->execute( $params );

		while ( false !== ($entity = $this->fetchObjectFromStatement( $className )) )
		{
			yield $entity;
		}
	}

	/**
	 * @throws QueryException
	 */
	public function fetchRow( array $params = [] ): array
	{
		$this->execute( $params );
		$rowData = $this->fetchFromStatement( \PDO::FETCH_ASSOC );

		if ( false === $rowData )
		{
			return [];
		}

		return $rowData;
	}

	/**
	 * @param array $params
	 *
	 * @return \Iterator<int, array>
	 * @throws QueryException
	 */
	public function fetchRows( array $params = [] ): \Iterator
	{
		$this->execute( $params );

		while ( false !== ($rowData = $this->fetchFromStatement( \PDO::FETCH_ASSOC )) )
		{
			yield $rowData;
		}
	}

	/**
	 * Requires data sorted by $groupColumn (otherwise, incorrect or broken groups may occur).
	 *
	 * @param string $groupColumn
	 * @param array  $params
	 *
	 * @return \Iterator<int|string|null,array> VALUE_OF_GROUP_COLUMN => [ associative arrays of the rows ]
	 * @throws QueryException
	 */
	public function fetchGroupedBy( string $groupColumn, array $params = [] ): \Iterator
	{
		$hasGroup     = false;
		$currentGroup = null;
		$groupRows    = [];
		$this->execute( $params );

		while ( false !== ($row = $this->fetchFromStatement( \PDO::FETCH_ASSOC )) )
		{
			$groupKey = $row[ $groupColumn ];

			if ( $hasGroup && $groupKey !== $currentGroup )
			{
				yield $currentGroup => $groupRows;
				$groupRows = [];
			}

			$groupRows[]  = $row;
			$currentGroup = $groupKey;
			$hasGroup     = true;
		}

		if ( $hasGroup )
		{
			yield $currentGroup => $groupRows;
		}
	}

	public function getAffectedRowCount(): int
	{
		return $this->pdoStatement->rowCount();
	}

	/**
	 * @throws QueryException
	 */
	public function execute( array $params ): \PDOStatement
	{
		try
		{
			$this->pdoStatement->execute( $params ? : null );
		}
		catch ( \PDOException $exception )
		{
			throw new QueryException( $this->pdoStatement->queryString, $params, $exception );
		}

		return $this->pdoStatement;
	}

	private function castToNullableString( mixed $value ): ?string
	{
		return null === $value ? null : (string)$value;
	}

	/**
	 * @throws QueryException
	 */
	private function fetchFromStatement( int $mode ): mixed
	{
		try
		{
			return $this->pdoStatement->fetch( $mode );
		}
		catch ( \PDOException $exception )
		{
			throw new QueryException( $this->pdoStatement->queryString, [], $exception );
		}
	}

	/**
	 * @throws QueryException
	 */
	private function fetchObjectFromStatement( string $className ): object|false
	{
		try
		{
			return $this->pdoStatement->fetchObject( $className );
		}
		catch ( \PDOException $exception )
		{
			throw new QueryException( $this->pdoStatement->queryString, [], $exception );
		}
	}
}
