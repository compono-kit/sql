<?php declare(strict_types=1);

namespace ComponoKit\Sql\Configs;

use ComponoKit\Sql\Configs\Interfaces\ConfiguresSqlManager;

class DefaultSqlManagerConfig implements ConfiguresSqlManager
{
	private array $configData;

	public function __construct( array $configData )
	{
		$this->configData = $configData;
	}

	/**
	 * @param string $filePathName
	 *
	 * @return static
	 */
	public static function fromFile( string $filePathName ): self
	{
		return new static( require $filePathName );
	}

	public function getDsn(): string
	{
		return sprintf(
			'mysql:host=%s;port=%d;dbname=%s',
			$this->configData['host'], $this->configData['port'], $this->configData['database']
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
		return $this->configData['charset'] ?? 'utf8';
	}

	public function getDriverOptions(): array
	{
		return $this->configData['options'] ?? [
			\PDO::ATTR_CURSOR                   => \PDO::CURSOR_FWDONLY,
			\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
			\PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET CHARACTER SET ' . $this->getCharset(),
			\PDO::ATTR_ERRMODE                  => \PDO::ERRMODE_EXCEPTION,
		];
	}
}
