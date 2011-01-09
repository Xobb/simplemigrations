<?php
/**
 * Migration class manages application database migrations.
 * Migration files should contain sql queries.
 * Usually UP migrations (the ones in up directory) add/modify/delete tables,
 * columns and constrains, therefore down migrations revert these changes.
 *
 * Example:
 * Your migrations should be placed in APPPATH migrations subfolder like that:
 *
 *	APPPATH.migrations/
 *	├── down
 *	│   ├── 1
 *	│   │   └── initial.sql
 *	│   └── 2
 *	│       └── user.sql
 *	└── up
 *	    ├── 1
 *	    │   └── initial.sql
 *	    └── 2
 *	        └── user.sql
 *
 * Note that migrations folder should be writable because database state is
 * stored in local filesystem. See configuration file for details.
 *
 * Assume we are on the first migration. The second migration consists of two
 * files. When calling migrate() each of them will be executed.
 *
 * The migration is a single transaction. If migration fails the database state
 * will be returned to the last successful migration.
 *
 * The migration order is assumed to be alphabetical, so you may use the letters
 * instead of digits.
 *
 * The only restriction is that the column, table or constrain comments cannot
 * have the semicolon (;) in their content, because it is used to split the
 * migration file contents into queries.
 *
 * @author	Paul Chubatyy <xobb@citylance.biz>
 */
class Kohana_Migration {

	/**
	 * The configuration used for the migration instance
	 */
	protected $config = array();

	/**
	 * The database migrations are operated on.
	 */
	protected $db;

	/**
	 * The path of the information storing file. It is defined in config
	 */
	protected $info_file;

	/**
	 * Current migration state of all databases.
	 */
	protected $state;

	/**
	 * All available migrations cache
	 */
	protected $migrations = array();

	/**
	 * Constructor. Throws the exception if migrations folder is not writable.
	 * @param	string	Configuration group
	 * @throws	Kohana_Exception
	 */
	public function __construct($config = 'default')
	{
		$this->config = Kohana::config('migrations')->$config;
		// If database is not set in config we assume default
		$this->db = Arr::get($this->config, 'database', 'default');

		// Check if migrations folder is writable
		if ( ! is_writable(APPPATH.'migrations'))
		{
			throw Kohana_Exception(
				'Migrations folder should be writable :folder',
				array(':folder' => APPPATH.'migrations')
			);
		}

		// Generate the path to the info file
		$this->info_file = APPPATH.'migrations' . DIRECTORY_SEPARATOR
			. Arr::get($this->config, 'info_file', '.version');

		// Read the current state of the schema
		$this->read_state();

		// Read the list of all available migrations
		$this->read_migrations();
	}

	/**
	 * Migrate the database to the version. If no version provided the database
	 * will be migrated to the newest up version available. Returns the string
	 * with the message about the actions taken.
	 * @param	integer
	 * @return	string
	 */
	public function migrate($target = NULL)
	{
		// If target version is not provided
		if ($target == NULL) {
			// We are migrating to the last version available
			$target = $this->get_newest_available_version();
		}

		$current = $this->get_current_version();

		// Retrieving the direction of the migrations
		if ($current > $target) {
			$direction = 'down';
		} elseif ($current < $target) {
			$direction = 'up';
		} else {
			return 'There is nothing to do!';
		}

		// Retrieving the migrations to be applied
		$migrations = $this->get_migrations($direction, $current, $target);

		foreach ($migrations as $version => $migration) {
			// Apply the migration
			$this->apply_migration($version, $migration);
		}

		$this->write_state();

		return __('We have successfully migrated from ' . $current
			. ' version to ' . $target . ' version.');
	}

	/**
	 * Get current schema version
	 * @return	integer
	 */
	public function get_current_version()
	{
		return Arr::get($this->state, $this->db, 0);
	}

	/**
	 * Set the current schema version
	 * @param	integer
	 * @return	Migration
	 */
	protected function set_current_version($version)
	{
		$this->state[$this->db] = $version;
		return $this;
	}

	/**
	 * Get the newest available migration version. If there aren't any
	 * migrations available throws the exception.
	 * @throws	Kohana_Exception
	 */
	protected function get_newest_available_version()
	{
		$up = Arr::get($this->migrations, 'up', array());
		if (empty($up)) {
			throw Kohana_Exception('There are not any migrations to proceed with. Please create migration first.');
		}
		$keys = array_keys($up);

		return max($keys);
	}

	/**
	 * Scan the migrations directory for all migrations available and cache them
	 * for later usage.
	 */
	protected function read_migrations()
	{
		// Great scan of the migrations directory
		foreach (Kohana::list_files('migrations') as $direction => $migrations) {
			// Get the direction of the migrations
			$direction = pathinfo($direction, PATHINFO_FILENAME);
			foreach ($migrations as $version => $files) {
				// Get the version of the migration
				$version = pathinfo($version, PATHINFO_FILENAME);
				// Add migration
				$this->add_migration($direction, $version, $files);
			}
		}
	}

	/**
	 * Add migration to the stack
	 * @param	string	up|down direction
	 * @param	integer	version
	 * @param	array 	array of file paths
	 */
	protected function add_migration($direction, $version, array $files)
	{
		$this->migrations[$direction] = Arr::get(
			$this->migrations,
			$direction,
			array()
		);

		$this->migrations[$direction][$version] = $files;
	}

	/**
	 * Get the ordered migration collection to be applied. Please note, that
	 * when migrating down the migrations are returned in reverse order so that
	 * they apply correctly.
	 * @param	string
	 * @param	integer
	 * @param	integer
	 * @return	array
	 */
	protected function get_migrations($direction, $current, $target)
	{
		// The array of the migrations in between of current and target versions
		$migrations = array();
		foreach (Arr::get($this->migrations, $direction, array()) as $version => $migration) {
			// The version must be in ($current, $target]
			if ($version > $current AND $version <= $target) {
				$migrations[$version] = $migration;
			}
		}
		// When downgrading we need to do the migrations in reverse order
		if ($direction == 'down') {
			return array_reverse($migrations);
		}
		return $migrations;
	}

	/**
	 * Execute the queries located in the migration files and set the database
	 * version to the one provided. If one of migration queries fails
	 * rolls back to the last successful migration applied.
	 * @param	integer	Migration version
	 * @param	array 	Files with the queries
	 * @throws	Kohana_Exception
	 */
	protected function apply_migration($version, array $files)
	{
		$queries = $this->get_queries_from_files($files);
		$db = Database::instance($this->db);
		// Initiate database transaction for migration
		$db->query(NULL, 'START TRANSACTION');
		try {
			foreach ($queries as $query) {
				if (trim($query) == '' OR trim($query) == PHP_EOL) {
					continue;
				}
				$db->query(NULL, trim($query));
			}
		} catch (Kohana_Exception $e) {
			// Rollback the migration
			$db->query(NULL, 'ROLLBACK');
			// Save the migrations applied
			$this->write_state();
			// Rethrow exception
			throw $e;
		}
		// Commit transaction
		$db->query(NULL, 'COMMIT');

		$this->set_current_version($version);
	}

	/**
	 * Retrieve queries from the migration files
	 * @param	array
	 * @return	array
	 */
	protected function get_queries_from_files(array $files)
	{
		$content = '';
		foreach ($files as $file) {
			// Concat the content of all the files
			$content .= file_get_contents($file);
		}

		return explode(';', $content);
	}

	/**
	 * Read the current database state
	 */
	protected function read_state()
	{
		if (file_exists($this->info_file)) {
			$handle = fopen($this->info_file, 'r');
			$data = fread($handle, filesize($this->info_file));
			fclose($handle);
			$this->state = json_decode($data, TRUE);
		}
	}

	/**
	 * Write the current database state
	 */
	protected function write_state()
	{
		$handle = fopen($this->info_file, 'w');
		$data = json_encode($this->state);
		fwrite($handle, $data);
		fclose($handle);
	}
}
