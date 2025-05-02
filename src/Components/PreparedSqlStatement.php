<?php declare(strict_types=1);

namespace ComponoKit\Sql\Components;

use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Interfaces\RepresentsEntity;
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
		$result = $this->execute( $params )
		               ->fetch( \PDO::FETCH_COLUMN );

		if ( false === $result )
		{
			$this->guardFetchResultIsNoFailure();

			return null;
		}

		return (string)$result;
	}

	/**
	 * @param array $params
	 *
	 * @return \Iterator<int,string>
	 * @throws QueryException
	 */
	public function fetchValues( array $params = [] ): \Iterator
	{
		$statement = $this->execute( $params );

		while ( $value = $statement->fetch( \PDO::FETCH_COLUMN ) )
		{
			yield $value;
		}
	}

	/**
	 * @throws QueryException
	 */
	public function fetchEntity( string $className, array $params = [] ): null|object|RepresentsEntity
	{
		$entity = $this->execute( $params )
		               ->fetchObject( $className );

		if ( false === $entity )
		{
			$this->guardFetchResultIsNoFailure();

			return null;
		}

		return $entity;
	}

	/**
	 * @param string $className
	 * @param array  $params
	 *
	 * @return \Iterator<int, object|RepresentsEntity>
	 * @throws QueryException
	 */
	public function fetchEntities( string $className, array $params = [] ): \Iterator
	{
		$statement = $this->execute( $params );

		while ( $entity = $statement->fetchObject( $className ) )
		{
			yield $entity;
		}
	}

	/**
	 * @throws QueryException
	 */
	public function fetchRow( array $params = [] ): array
	{
		$statement = $this->execute( $params );
		$rowData   = $statement->fetch( \PDO::FETCH_ASSOC );

		if ( false === $rowData )
		{
			$this->guardFetchResultIsNoFailure();

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
		$statement = $this->execute( $params );

		while ( $rowData = $statement->fetch( \PDO::FETCH_ASSOC ) )
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
	 * @return \Iterator<int,array> VALUE_OF_GROUP_COLUMN => [ associative arrays of the rows ]
	 * @throws QueryException
	 */
	public function fetchGroupedBy( string $groupColumn, array $params = [] ): \Iterator
	{
		$currentGroup = null;
		$groupRows    = [];
		$statement    = $this->execute( $params );

		while ( $row = $statement->fetch( \PDO::FETCH_ASSOC ) )
		{
			$groupKey = $row[ $groupColumn ];

			if ( null !== $currentGroup && $groupKey !== $currentGroup )
			{
				yield $currentGroup => $groupRows;
				$groupRows = [];
			}

			$groupRows[]  = $row;
			$currentGroup = $groupKey;
		}

		if ( null !== $currentGroup )
		{
			yield $currentGroup => $groupRows;
		}
	}

	private function execute( array $params ): \PDOStatement
	{
		try
		{
			if ( !@$this->pdoStatement->execute( $params ? : null ) || $this->pdoStatement->errorCode() > 0 )
			{
				throw (new QueryException( $this->pdoStatement->errorInfo()[2] ))->withErrors( $this->pdoStatement->errorInfo() )
				                                                                 ->withQuery( $this->pdoStatement->queryString );
			}

			return $this->pdoStatement;
		}
		catch ( \PDOException )
		{
			throw (new QueryException( $this->pdoStatement->errorInfo()[2] ))->withErrors( $this->pdoStatement->errorInfo() )
			                                                                 ->withQuery( $this->pdoStatement->queryString )
			                                                                 ->withPreparedParameters( $params );
		}
	}

	private function guardFetchResultIsNoFailure(): void
	{
		if ( $this->pdoStatement->errorCode() > 0 )
		{
			throw (new QueryException( $this->pdoStatement->errorInfo()[2] ))->withErrors( $this->pdoStatement->errorInfo() )
			                                                                 ->withQuery( $this->pdoStatement->queryString );
		}
	}
}
