<?php declare(strict_types=1);

namespace ComponoKit\Sql;

use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Interfaces\RepresentsPreparedStatement;

class MySqlManager extends SqlManager
{
	public function prepare( string $query ): RepresentsPreparedStatement
	{
		try
		{
			return parent::prepare( $query );
		}
		catch ( QueryException $exception )
		{
			if ( 2006 === $exception->getErrors()[1] )
			{
				$this->connect();

				return $this->prepare( $query );
			}

			throw $exception;
		}
	}
}
