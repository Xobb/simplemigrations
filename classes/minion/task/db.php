<?php
/**
 * 
 * @author: xobb
 * Date: 10/21/11
 * Time: 8:29 PM 
 */
 
class Minion_Task_Db extends Minion_Task {

	protected $actions_available = array(
		'diff', 'show', 'migrate',
	);

	protected $data = array();

	protected $filename;

	protected $_params;

	public function execute(array $params)
	{
		$params = Arr::extract($params, $this->_config);
		$this->db = Database::instance(Arr::get($params, 'database', 'default'));

		$this->database = Arr::get($params, 'database', 'default');
		if (array_key_exists('database', $params))
			unset($params['database']);

		foreach ($params as $key => $value)
			$this->_params[$key] = $value;

		$this->read_current_version();

		$action = $this->get_param('action', 'list_actions');
		if (method_exists($this, $action))
			call_user_func(array($this, $action));
		else
			$this->list_actions();
	}

	protected function migrate()
	{
		$direction = $this->get_param('direction', 'up');
		$migrations = $this->get_migrations($direction);
		foreach ($migrations as $migration) {
			$migration = $this->get_migration_by_name($migration);
			try {
				// Start transaction
				$this->db->begin();
				// Execute the migration
				$migration->set_db($this->db);
				call_user_func(array($migration, 'pre_'.$direction));
				call_user_func(array($migration, $direction));
				call_user_func(array($migration, 'post_'.$direction));
				// Commit changes
				$this->db->commit();
				// Mark transaction as applied or reverted
				$this->set_migration($migration, $direction);
				// Write down the migrations applied
				$this->write_applied_migrations();
			} catch (Kohana_Exception $e) {
				// Rollback the transaction
				$this->db->rollback();
				// Rethrow Exception
				throw $e;
			}
		}
	}

	protected function list_actions()
	{
		Minion_CLI::write('List of actions available:');
		Minion_CLI::write('==========================');
		Minion_CLI::write(array($this->actions_available));
	}

	protected function read_current_version()
	{
		$this->filename = Kohana::find_file('migration', $this->database, 'json');
		if (!$this->filename)
			$this->filename = APPPATH.'migrations/'.$this->database.'.json';
		$data = file_get_contents($this->filename);
		$this->data = json_decode($data);
	}

	protected function write_current_version()
	{
		if (!is_writable($this->filename))
			throw Kohana_Exception('File :file must be writable', array(':file' => $this->filename));

		file_put_contents($this->filename, json_encode($this->data));
	}

	protected function get_param($key, $default)
	{
		return Arr::get($this->_params, $key, $default);
	}

	protected function get_migrations($direction)
	{
		$available_migrations = Kohana::list_files('class/migration');
		$applied_migrations = $this->get_applied_migrations();
		$migrations = array();
		foreach ($available_migrations as $migration) {
			if ($direction == self::UP && !in_array($migration, $applied_migrations)) {
				$migrations[] = $migration;
				continue;
			}

			if ($direction == self::DOWN && in_array($migration, $applied_migrations)) {
				$migrations[] = $migration;
				continue;
			}
		}
		return $migrations;
	}

	protected function get_applied_migrations()
	{
		return Arr::get($this->data, 'migrations');
	}

	protected function set_migration(Semi_Migration $migration, $direction)
	{
		$migrations = Arr::get($this->data, 'migrations', array());
		if ($direction == self::UP) {
			$migrations[] = $migration->get_name();
			$this->data['migrations'] = $migration;
		} else {
			foreach ($migration as $key => $applied_migration)
				if ($applied_migration == $migration->get_version()) {
					unset($migrations[$key]);
					break;
				}
		}
	}

	/**
	 * Returns the migration 
	 * @param  $name
	 * @return Simi_Migration
	 */
	protected function get_migration_by_name($name)
	{
		$version = pathinfo(pathinfo($name, PATHINFO_DIRNAME), PATHINFO_BASENAME);
		$name = pathinfo($name, PATHINFO_FILENAME);
		return Simi_Migration::instance($version.'_'.$name);
	}
}
