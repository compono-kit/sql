<?php declare(strict_types=1);

namespace ComponoKit\Sql\Configs\Interfaces;

interface ConfiguresSqlManager
{
	public function getDsn(): string;

	public function getUser(): string;

	public function getPassword(): string;

	public function getCharset(): string;

	public function getDriverOptions(): array;
}
