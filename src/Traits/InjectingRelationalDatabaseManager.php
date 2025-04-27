<?php declare(strict_types=1);

namespace ComponoKit\Sql\Traits;

use ComponoKit\Sql\Interfaces\ManagesRelationalDatabases;

trait InjectingRelationalDatabaseManager
{
	private ManagesRelationalDatabases $dbManager;

	public function __construct( private ManagesRelationalDatabases $sqlManager )
	{
	}
}
