<?php
/**
 * 
 * @author: xobb
 * Date: 10/19/11
 * Time: 11:04 PM 
 */
 
class Simi_Migration {

	protected static $instances;

	public static function instance($name)
	{
		if (!array_key_exists($name, self::$instances)) {
			$class_name = 'Migration_'.$name;
			self::$instances[$name] = new $class_name;
		}
		return self::$instances[$name];
	}

	protected function __construct()
	{

	}

	public function set_db(Database $db)
	{
		$this->db = $db;
		return $this;
	}

	public function get_name()
	{
		$name = get_class($this);
		$name = explode('_', $name);
		array_shit($name);
		return implode('/', $name);
	}

	public function get_version()
	{
		$version = get_class($this);
		$version = explode('_', $version);
		return Arr::get($version, 1);
	}

	public function pre_up()
	{
		return $this;
	}

	public function up()
	{
		return $this;
	}

	public function post_up()
	{
		return $this;
	}

	public function pre_down()
	{
		return $this;
	}

	public function down()
	{
		return $this;
	}

	public function post_down()
	{
		return $this;
	}
}
