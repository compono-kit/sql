<?php declare(strict_types=1);

namespace ComponoKit\Sql\Tests\Integration\Components;

use ComponoKit\Sql\Configs\DefaultSqlManagerConfig;
use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\SqlManager;
use ComponoKit\Sql\Tests\fixtures\UserEntity;
use PHPUnit\Framework\TestCase;

class PreparedSqlStatementTest extends TestCase
{
	public static function setUpBeforeClass(): void
	{
		$pdo = new \PDO( 'mysql:host=sql_mariadb;port=3306;dbname=', 'root', 'root' );
		$pdo->exec( 'DROP DATABASE IF EXISTS `sql_lib_test`' );
		$pdo->exec( 'CREATE DATABASE `sql_lib_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
		$pdo->exec( 'USE `sql_lib_test`' );
		$pdo->exec( 'DROP TABLE IF EXISTS test_users' );
		$pdo->exec(
			'
			CREATE TABLE test_users (
				id INT AUTO_INCREMENT PRIMARY KEY,
				name VARCHAR(100),
				role VARCHAR(50)
			)
		'
		);

		$pdo->exec( "INSERT INTO test_users (name, role) VALUES ('Alice', 'admin'), ('Bob', 'user'), ('Charlie', 'user')" );
	}

	public static function tearDownAfterClass(): void
	{
		$pdo = new \PDO( 'mysql:host=sql_mariadb;port=3306;dbname=', 'root', 'root' );
		$pdo->exec( 'DROP DATABASE IF EXISTS `sql_lib_test`' );
	}

	public function testFetchValue(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt  = $sqlManager->prepare( 'SELECT name FROM test_users WHERE id = :id' );
		$value = $stmt->fetchValue( [ 'id' => 1 ] );

		$this->assertEquals( 'Alice', $value );
	}

	public function testFetchValues(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt   = $sqlManager->prepare( 'SELECT name FROM test_users ORDER BY id' );
		$values = iterator_to_array( $stmt->fetchValues() );

		$this->assertEquals( [ 'Alice', 'Bob', 'Charlie' ], $values );
	}

	public function testFetchRow(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt = $sqlManager->prepare( 'SELECT * FROM test_users WHERE name = :name' );
		$row  = $stmt->fetchRow( [ 'name' => 'Bob' ] );

		$this->assertEquals( 'Bob', $row['name'] );
		$this->assertEquals( 'user', $row['role'] );
	}

	public function testFetchRows(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt = $sqlManager->prepare( 'SELECT * FROM test_users ORDER BY id' );
		$rows = iterator_to_array( $stmt->fetchRows() );

		$this->assertCount( 3, $rows );
		$this->assertEquals( 'Charlie', $rows[2]['name'] );
	}

	public function testFetchEntity(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt   = $sqlManager->prepare( 'SELECT * FROM test_users WHERE name = :name' );
		$entity = $stmt->fetchEntity( UserEntity::class, [ 'name' => 'Alice' ] );

		$this->assertEquals( 'Alice', $entity->name );
		$this->assertEquals( 'admin', $entity->role );
	}

	public function testFetchEntities(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt     = $sqlManager->prepare( 'SELECT * FROM test_users ORDER BY id' );
		$entities = iterator_to_array( $stmt->fetchEntities( UserEntity::class ) );

		$this->assertCount( 3, $entities );
		$this->assertEquals( 'Charlie', $entities[2]->name );
	}

	public function testFetchGroupedBy(): void
	{
		$sqlManager = $this->buildSqlManager();

		$stmt   = $sqlManager->prepare( 'SELECT * FROM test_users ORDER BY role, id' );
		$groups = iterator_to_array( $stmt->fetchGroupedBy( 'role' ) );

		$this->assertArrayHasKey( 'admin', $groups );
		$this->assertArrayHasKey( 'user', $groups );

		$this->assertCount( 1, $groups['admin'] );
		$this->assertCount( 2, $groups['user'] );
	}

	public function testPrepareInvalidQueryThrowsException(): void
	{
		$sqlManager = $this->buildSqlManager();

		$this->expectException( QueryException::class );
		$stmt = $sqlManager->prepare( 'SELECT * FROM invalid_table' );
		$stmt->fetchRow();
	}

	private function buildSqlManager( bool $emulatedPrepares = true ): SqlManager
	{
		return new SqlManager(
			new DefaultSqlManagerConfig(
				[
					'host'     => 'sql_mariadb',
					'database' => 'sql_lib_test',
					'user'     => 'root',
					'password' => 'root',
					'port'     => 3306,
					'charset'  => 'utf8mb4',
					'options'  => [
						\PDO::ATTR_EMULATE_PREPARES         => $emulatedPrepares,
						\PDO::ATTR_CURSOR                   => \PDO::CURSOR_FWDONLY,
						\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						\PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET CHARACTER SET utf8',
						\PDO::ATTR_ERRMODE                  => \PDO::ERRMODE_EXCEPTION,
					],
				]
			)
		);
	}
}
