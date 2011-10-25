<?php
/**
 * 
 * @author: xobb
 * Date: 10/19/11
 * Time: 11:06 PM 
 */
 
class Simi_Migrator {

	protected static $instances = array();

	public static function instance($database = 'default')
	{
		if (!array_key_exists($database, self::$instances)) {
			self::$instances = new Migrator($database);
		}
		return self::$instances[$database];
	}

	protected $database;

	/**
	 * @var \Database
	 */
	protected $db;

	protected $applied_migrations = array();

	protected $versions = array();

	protected function __construct($database)
	{
		$this->database = $database;
		$this->db = Database::instance($this->database);

		// Read all available migrations
		$this->read_available_migrations();
		// Read all applied migrations
		$this->read_appied_migrations();
	}

	public function migration_applied(Simi_Migration $migration)
	{
		return in_array($migration->get_name(), $this->applied_migrations);
	}

	/**
	 * Applies the migration onto the database
	 * @throws Kohana_Exception
	 * @param Simi_Migration $migration
	 * @return void
	 */
	public function apply($migration)
	{

	}

	/**
	 * Reverts the migration 
	 * @throws Kohana_Exception
	 * @param Simi_Migration $migration
	 * @return void
	 */
	public function revert($migration)
	{
		try {
			// Start transaction
			$this->db->begin();
			// Execute the migration
			$migration->set_db($this->db)->pre_down()->down()->post_down();
			// Commit changes
			$this->db->commit();
			// Mark transaction as applied
			$this->applied_migrations[] = $migration->get_name();
			// Write down the migrations applied
			$this->write_applied_migrations();
		} catch (Kohana_Exception $e) {
			// Rollback the transaction
			$this->db->rollback();
			// Rethrow Exception
			throw $e;
		}
	}

	protected function read_available_migrations()
	{
		$versions = Kohana::list_files('migrations');
		foreach($versions as $path) {
			$this->version[] = new Simi_Version($path, $this);
		}
	}

	protected function read_applied_migrations()
	{
		$file = Kohana::find_file('migrations', $this->database, 'json');
		if (!$file)
			return;

		$file = file_get_contents($file);
		$this->applied_migrations = json_decode($file, true);
	}

	protected function write_applied_migrations()
	{
		$migrations = $this->applied_migrations + $this->added_migrations;
		$migrations = json_encode($migrations);

		$file = APPPATH.'migrations/'.$this->database.'.json';
		if (!is_writable($file))
			throw Kohana_Exception('File :file must be writable', array(':file' => $file));

		file_put_contents($file, $migrations);
	}
}
