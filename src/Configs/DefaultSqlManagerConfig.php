<?php declare(strict_types=1);

namespace ComponoKit\Sql\Configs;

use ComponoKit\Sql\Configs\Interfaces\ConfiguresSqlManager;

class DefaultSqlManagerConfig implements ConfiguresSqlManager
{
	private const REQUIRED_KEYS   = [ 'host', 'database', 'user', 'password' ];

	private const DEFAULT_PORT    = 3306;

	private const DEFAULT_CHARSET = 'utf8mb4';

	private array $configData;

	public function __construct( array $configData )
	{
		foreach ( self::REQUIRED_KEYS as $requiredKey )
		{
			if ( !array_key_exists( $requiredKey, $configData ) )
			{
				throw new \InvalidArgumentException( sprintf( 'Missing required config key "%s"', $requiredKey ) );
			}
		}

		$this->configData = $configData;
	}

	public static function fromFile( string $filePathName ): static
	{
		return new static( require $filePathName );
	}

	public function getDsn(): string
	{
		return sprintf(
			'mysql:host=%s;port=%d;dbname=%s;charset=%s',
			$this->configData['host'],
			$this->configData['port'] ?? self::DEFAULT_PORT,
			$this->configData['database'],
			$this->getCharset()
		);
	}

	public function getUser(): string
	{
		return $this->configData['user'];
	}

	public function getPassword(): string
	{
		return $this->configData['password'];
	}

	public function getCharset(): string
	{
		return $this->configData['charset'] ?? self::DEFAULT_CHARSET;
	}

	public function getDriverOptions(): array
	{
		return ($this->configData['options'] ?? []) + [
				\PDO::ATTR_CURSOR                   => \PDO::CURSOR_FWDONLY,
				\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
			];
	}
}
