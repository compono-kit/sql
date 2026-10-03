<?php declare(strict_types=1);

namespace ComponoKit\Sql\Tests\Integration;

use ComponoKit\Sql\Configs\DefaultSqlManagerConfig;
use ComponoKit\Sql\Exceptions\TransactionLogicException;
use ComponoKit\Sql\Interfaces\ManagesTransactions;
use ComponoKit\Sql\SqlManager;
use ComponoKit\Sql\TransactionManager;
use PHPUnit\Framework\TestCase;

class TransactionManagerTest extends TestCase
{
	private SqlManager         $sqlManager;

	private TransactionManager $transactionManager;

	public static function setUpBeforeClass(): void
	{
		$pdo = new \PDO( 'mysql:host=sql_mariadb;port=3306;dbname=', 'root', 'root' );
		$pdo->exec( 'DROP DATABASE IF EXISTS `sql_lib_test`' );
		$pdo->exec( 'CREATE DATABASE `sql_lib_test` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci' );
	}

	public static function tearDownAfterClass(): void
	{
		$pdo = new \PDO( 'mysql:host=sql_mariadb;port=3306;dbname=', 'root', 'root' );
		$pdo->exec( 'DROP DATABASE IF EXISTS `sql_lib_test`' );
	}

	protected function setUp(): void
	{
		$this->sqlManager = new SqlManager(
			new DefaultSqlManagerConfig(
				[
					'host'     => 'sql_mariadb',
					'database' => 'sql_lib_test',
					'user'     => 'root',
					'password' => 'root',
				]
			)
		);

		$this->sqlManager->getPdo()->exec( 'DROP TABLE IF EXISTS test_transactions' );
		$this->sqlManager->getPdo()->exec( 'CREATE TABLE test_transactions (id INT PRIMARY KEY) ENGINE=InnoDB' );

		$this->transactionManager = new TransactionManager( $this->sqlManager );
	}

	protected function tearDown(): void
	{
		if ( $this->sqlManager->inTransaction() )
		{
			$this->sqlManager->rollBack();
		}

		$this->sqlManager->getPdo()->exec( 'DROP TABLE IF EXISTS test_transactions' );
	}

	public function testBeginAndCommit(): void
	{
		$this->transactionManager->begin();

		$this->assertTrue( $this->transactionManager->inTransaction() );
		$this->assertSame( 1, $this->transactionManager->getTransactionLevel() );

		$this->insertId( 1 );
		$this->transactionManager->commit();

		$this->assertFalse( $this->transactionManager->inTransaction() );
		$this->assertSame( [ '1' ], $this->fetchIds() );
	}

	public function testBeginAndRollBack(): void
	{
		$this->transactionManager->begin();
		$this->insertId( 1 );
		$this->transactionManager->rollBack();

		$this->assertFalse( $this->transactionManager->inTransaction() );
		$this->assertSame( [], $this->fetchIds() );
	}

	public function testNestedCommit(): void
	{
		$this->transactionManager->begin();
		$this->insertId( 1 );
		$this->transactionManager->begin();

		$this->assertSame( 2, $this->transactionManager->getTransactionLevel() );

		$this->insertId( 2 );
		$this->transactionManager->commit();
		$this->transactionManager->commit();

		$this->assertSame( [ '1', '2' ], $this->fetchIds() );
	}

	public function testNestedRollBackKeepsOuterChanges(): void
	{
		$this->transactionManager->begin();
		$this->insertId( 1 );
		$this->transactionManager->begin();
		$this->insertId( 2 );
		$this->transactionManager->rollBack();

		$this->assertSame( 1, $this->transactionManager->getTransactionLevel() );

		$this->insertId( 3 );
		$this->transactionManager->commit();

		$this->assertSame( [ '1', '3' ], $this->fetchIds() );
	}

	public function testOuterRollBackDiscardsCommittedInnerChanges(): void
	{
		$this->transactionManager->begin();
		$this->insertId( 1 );
		$this->transactionManager->begin();
		$this->insertId( 2 );
		$this->transactionManager->commit();
		$this->transactionManager->rollBack();

		$this->assertSame( [], $this->fetchIds() );
	}

	public function testTransactionalCommitsAndReturnsResult(): void
	{
		$result = $this->transactionManager->transactional(
			function ( ManagesTransactions $transactionManager ): string
			{
				$this->insertId( 1 );

				return 'done';
			}
		);

		$this->assertSame( 'done', $result );
		$this->assertFalse( $this->transactionManager->inTransaction() );
		$this->assertSame( [ '1' ], $this->fetchIds() );
	}

	public function testTransactionalRollsBackOnException(): void
	{
		try
		{
			$this->transactionManager->transactional(
				function (): void
				{
					$this->insertId( 1 );

					throw new \DomainException( 'Failure' );
				}
			);

			$this->fail( 'Expected exception was not thrown' );
		}
		catch ( \DomainException $exception )
		{
			$this->assertSame( 'Failure', $exception->getMessage() );
		}

		$this->assertFalse( $this->transactionManager->inTransaction() );
		$this->assertSame( [], $this->fetchIds() );
	}

	public function testNestedTransactionalRollsBackOnlyInnerLevel(): void
	{
		$this->transactionManager->transactional(
			function ( ManagesTransactions $transactionManager ): void
			{
				$this->insertId( 1 );

				try
				{
					$transactionManager->transactional(
						function (): void
						{
							$this->insertId( 2 );

							throw new \DomainException( 'Failure' );
						}
					);
				}
				catch ( \DomainException )
				{
				}
			}
		);

		$this->assertSame( [ '1' ], $this->fetchIds() );
	}

	public function testCommitWithoutTransactionThrows(): void
	{
		$this->expectException( TransactionLogicException::class );
		$this->transactionManager->commit();
	}

	public function testRollBackWithoutTransactionThrows(): void
	{
		$this->expectException( TransactionLogicException::class );
		$this->transactionManager->rollBack();
	}

	public function testBeginThrowsIfTransactionWasStartedOutside(): void
	{
		$this->sqlManager->beginTransaction();

		$this->expectException( TransactionLogicException::class );
		$this->transactionManager->begin();
	}

	private function insertId( int $id ): void
	{
		$this->sqlManager->execute( 'INSERT INTO test_transactions (id) VALUES (:id)', [ 'id' => $id ] );
	}

	private function fetchIds(): array
	{
		return iterator_to_array( $this->sqlManager->prepare( 'SELECT id FROM test_transactions ORDER BY id' )->fetchValues() );
	}
}
