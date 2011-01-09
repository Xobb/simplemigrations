<?php
/**
 * DB:Information task is used to show the database status,
 * available migrations and information about particular migration.
 * See examples usage examples below:
 *
 *	Get the database status
 *
 *		php index.php --uri=minion/db:information --action=status
 *
 *	This is default action if run without any params:
 *
 *		php index.php --uri=minion/db:information
 *
 *	Show the list of files that contain the database queries
 *	in order they will be executed:
 *
 *		php index.php --uri=minion/db:information --action=diff
 *		--current=[integer] --target=[integer]
 *
 *	Show the files that form particular migration version:
 *
 *		php index.php --uri=minion/db:information --action=migration
 *		--current=[integer]
 *
 *	Also you may set the database group the migration facility should
 *	inform you about with
 *
 *		--database=[groupname]
 *
 *	param to any of the commands above.
 *
 * @package	Minion
 * @author Paul Chubatyy <xobb@citylance.biz>
 */
class Minion_Task_Db_Information extends Minion_Task {

	protected $_config = array(
		'database', 'action', 'current', 'target'
	);

	public function execute(array $params)
	{
		$migration = new Migration(Arr::get($params, 'database'));
		$migration->information($params);
	}
}
