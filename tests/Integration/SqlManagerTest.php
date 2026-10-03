<?php declare(strict_types=1);

namespace ComponoKit\Sql\Tests\Integration;

use ComponoKit\Sql\Configs\DefaultSqlManagerConfig;
use ComponoKit\Sql\Exceptions\QueryException;
use ComponoKit\Sql\Exceptions\TransactionLogicException;
use ComponoKit\Sql\Exceptions\TransactionRuntimeException;
use ComponoKit\Sql\Interfaces\RepresentsPreparedStatement;
use ComponoKit\Sql\SqlManager;
use PHPUnit\Framework\TestCase;

class SqlManagerTest extends TestCase
{
	private static string $tmpDumpFile;

	public static function setUpBeforeClass(): void
	{
		self::$tmpDumpFile = __DIR__ . '/dump.sql';

		$pdo = new \PDO( 'mysql:host=sql_mariadb;port=3306;dbname=', 'root', 'root' );
		$pdo->exec( 'DROP DATABASE IF EXISTS `sql_lib_test`' );
		$pdo->exec( 'CREATE DATABASE `sql_lib_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
	}

	public static function tearDownAfterClass(): void
	{
		$pdo = new \PDO( 'mysql:host=sql_mariadb;port=3306;dbname=', 'root', 'root' );
		$pdo->exec( 'DROP DATABASE IF EXISTS `sql_lib_test`' );

		if ( is_file( self::$tmpDumpFile ) )
		{
			unlink( self::$tmpDumpFile );
		}
	}

	public function testConnectAndIsConnected(): void
	{
		$sqlManager = $this->buildSqlManager();

		$this->assertFalse( $sqlManager->isConnected() );
		$sqlManager->connect();
		$this->assertTrue( $sqlManager->isConnected() );
	}

	public function testClose(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->connect();
		$sqlManager->close();
		$this->assertFalse( $sqlManager->isConnected() );
	}

	public function testBeginCommitTransaction(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->connect();
		$sqlManager->getPdo()->exec( 'CREATE TABLE test_table (id INT PRIMARY KEY)' );
		$sqlManager->beginTransaction();
		$this->assertTrue( $sqlManager->inTransaction() );
		$sqlManager->execute( 'INSERT INTO test_table (id) VALUES (:id)', [ 'id' => 1 ] );
		$sqlManager->commit();
		$this->assertFalse( $sqlManager->inTransaction() );
		$this->assertEquals( 1, $sqlManager->getPdo()->query('SELECT * FROM test_table WHERE id = 1')->fetchColumn() );

		$sqlManager->getPdo()->exec('DROP TABLE test_table');
	}

	public function testBeginRollBackTransaction(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->connect();
		$sqlManager->getPdo()->exec( 'CREATE TABLE test_table (id INT PRIMARY KEY)' );
		$sqlManager->beginTransaction();
		$this->assertTrue( $sqlManager->inTransaction() );
		$sqlManager->execute( 'INSERT INTO test_table (id) VALUES (:id)', [ 'id' => 1 ] );
		$sqlManager->rollBack();
		$this->assertFalse( $sqlManager->inTransaction() );
		$this->assertFalse( $sqlManager->getPdo()->query('SELECT * FROM test_table WHERE id = 1')->fetchColumn() );

		$sqlManager->getPdo()->exec('DROP TABLE test_table');
	}

	public function testPrepareValidQuery(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->connect();
		$sqlManager->getPdo()->exec( 'CREATE TABLE test_table (id INT PRIMARY KEY)' );
		$statement = $sqlManager->prepare( 'SELECT * FROM test_table' );
		$this->assertInstanceOf( RepresentsPreparedStatement::class, $statement );

		$sqlManager->getPdo()->exec('DROP TABLE test_table');
	}

	public function testPrepareInvalidQueryThrows(): void
	{
		$sqlManager = $this->buildSqlManager( false );

		$sqlManager->connect();
		$this->expectException( QueryException::class );
		$sqlManager->prepare( 'INVALID SQL' );
	}

	public function testImportDump(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->connect();

		$dumpContent = 'CREATE TABLE test_import (id INT PRIMARY KEY); 
						INSERT INTO test_import (id) VALUES (1);';

		file_put_contents( self::$tmpDumpFile, $dumpContent );

		$sqlManager->importDump( self::$tmpDumpFile );

		$result = $sqlManager->getPdo()->query( 'SELECT * FROM test_import' )->fetchAll( \PDO::FETCH_ASSOC );
		$this->assertCount( 1, $result );
		$this->assertEquals( [ 'id' => 1 ], $result[0] );

		unlink( self::$tmpDumpFile );
	}

	public function testExecuteReturnsAffectedRowCount(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->getPdo()->exec( 'CREATE TABLE test_table (id INT PRIMARY KEY)' );

		$this->assertSame( 2, $sqlManager->execute( 'INSERT INTO test_table (id) VALUES (1), (2)' ) );
		$this->assertSame( 1, $sqlManager->execute( 'DELETE FROM test_table WHERE id = :id', [ 'id' => 1 ] ) );

		$sqlManager->getPdo()->exec( 'DROP TABLE test_table' );
	}

	public function testLastInsertId(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->getPdo()->exec( 'CREATE TABLE test_table (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(10))' );
		$sqlManager->execute( 'INSERT INTO test_table (name) VALUES (:name)', [ 'name' => 'first' ] );
		$sqlManager->execute( 'INSERT INTO test_table (name) VALUES (:name)', [ 'name' => 'second' ] );

		$this->assertSame( '2', $sqlManager->lastInsertId() );

		$sqlManager->getPdo()->exec( 'DROP TABLE test_table' );
	}

	public function testExecuteInvalidQueryThrows(): void
	{
		$sqlManager = $this->buildSqlManager( false );

		$this->expectException( QueryException::class );
		$sqlManager->execute( 'INSERT INTO not_existing_table (id) VALUES (1)' );
	}

	public function testBeginTransactionTwiceThrows(): void
	{
		$sqlManager = $this->buildSqlManager();

		$sqlManager->beginTransaction();

		$this->expectException( TransactionRuntimeException::class );
		$sqlManager->beginTransaction();
	}

	public function testCommitWithoutTransactionThrows(): void
	{
		$sqlManager = $this->buildSqlManager();

		$this->expectException( TransactionLogicException::class );
		$sqlManager->commit();
	}

	public function testRollBackWithoutTransactionThrows(): void
	{
		$sqlManager = $this->buildSqlManager();

		$this->expectException( TransactionLogicException::class );
		$sqlManager->rollBack();
	}

	public function testImportNotExistingDumpThrows(): void
	{
		$sqlManager = $this->buildSqlManager();

		$this->expectException( \RuntimeException::class );
		$sqlManager->importDump( __DIR__ . '/not_existing_dump.sql' );
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
