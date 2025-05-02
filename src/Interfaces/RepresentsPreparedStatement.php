<?php declare(strict_types=1);

namespace ComponoKit\Sql\Interfaces;

use ComponoKit\Sql\Exceptions\QueryException;

interface RepresentsPreparedStatement
{
	/**
	 * @throws QueryException
	 */
	public function fetchValue( array $params = [] ): ?string;

	/**
	 * @param array $params
	 *
	 * @return \Iterator<int, string>
	 * @throws QueryException
	 */
	public function fetchValues( array $params = [] ): \Iterator;

	/**
	 * @throws QueryException
	 */
	public function fetchEntity( string $className, array $params = [] ): ?object;

	/**
	 * @param string $className
	 * @param array  $params
	 *
	 * @return \Iterator<int, object>
	 * @throws QueryException
	 */
	public function fetchEntities( string $className, array $params = [] ): \Iterator;

	/**
	 * @throws QueryException
	 */
	public function fetchRow( array $params = [] ): array;

	/**
	 * @param array $params
	 *
	 * @return \Iterator<int, array>
	 * @throws QueryException
	 */
	public function fetchRows( array $params = [] ): \Iterator;

	/**
	 * @param string $groupColumn
	 * @param array  $params
	 *
	 * @return \Iterator<int,array> VALUE_OF_GROUP_COLUMN => [ associative arrays of the rows ]
	 * @throws QueryException
	 */
	public function fetchGroupedBy( string $groupColumn, array $params = [] ): \Iterator;
}
