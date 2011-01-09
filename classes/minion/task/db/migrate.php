<?php

/**
 * Minion task for simplemigrations module
 *
 * Initiates the database migrations.
 *
 * Params:
 * --version=###		The version to migrate the database to
 * --database=<string>	The database to run migrations on.
 *
 * Usage examples:
 *
 *	Migrate to particular version 7:
 *
 *		php index.php --uri=minion/db:migrate --version=7
 *
 *	Migrate to the latest available version:
 *
 *		php index.php --uri=minion/db:migrate
 *
 * @package	Minion
 * @author	Paul Chubatyy <xobb@citylance.biz>
 */
class Minion_Task_Db_Migrate extends Minion_Task {
	protected $_config = array(
		'version', 'database'
	);

	public function execute(array $config)
	{
		extract(Arr::extract($config, $this->_config));

		$migration = new Migration($database);
		$migration->migrate($version);
	}
}
