<?php
/**
 * 
 * @author: xobb
 * Date: 10/19/11
 * Time: 11:04 PM 
 */
 
class Simi_Migration {

	public static function factory($name, Simi_Version $version)
	{
		require Kohana::find_file('migrations', $name);
		$class_name = str_replace('/', '_', $name);
		return new $class_name($version);
	}

	protected $version;

	public function __construct(Simi_Version $version)
	{
		$this->version = $version;
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
		$name = array_pop($name);
		return $this->version->get_name() . '/' . $name;
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
