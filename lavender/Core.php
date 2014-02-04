<?php
namespace Lavender;

//init
Core::init();

final class Core
{
	/**
	 * server env
	 * @const string
	 */
	const ENV_DEVELOP = 'develop';
	const ENV_TEST = 'test';
	const ENV_WORK = 'work';

	private static $namespace_map = array();
	private static $config_cache = array();
	private static $db_instances = array();
	private static $language_cache;

	/**
	 * init Lavender
	 * running on load this file
	 *
	 * @return void
	 */
	public static function init()
	{
		if (!defined('L_WORKSPACE_PATH')) {
			throw new \Exception('L_WORKSPACE_PATH undefined,please define it in init.php', Errno::CONST_UNDEFINED);
		}

		if (!defined('L_APP_PATH')) {
			throw new \Exception('L_APP_PATH undefined,please define it in init.php', Errno::CONST_UNDEFINED);
		}

		if (!defined('L_ENV')) {
			throw new \Exception('L_ENV undefined,please define it in init.php', Errno::CONST_UNDEFINED);
		}

		//auto define consts
		define('L_EXT_PATH', L_WORKSPACE_PATH . 'ext/');
		define('L_APP_NAME', basename(L_APP_PATH) );

		//register autoload
		self::$namespace_map['Lavender'] = __DIR__ . '/'; //framework
		self::$namespace_map['App'] = L_APP_PATH; //app

		spl_autoload_register('\Lavender\Core::autoload');
	}

	/**
	 * autoload register
	 * warring: can not cover the registered
	 *
	 * @param string $namespace 	root namespace
	 * @param string $path 			the root namespace path
	 *
	 * @return void
	 */
	public static function register_autoload($namespace, $path)
	{
		if (empty(self::$namespace_map[$namespace])) {
			self::$namespace_map[$namespace] = $path;
			return true;
		}

		return false;
	}

	/**
	 * Lavender autoload function
	 * call this function on not found the class
	 *
	 * @param string $class_name
	 *
	 * @return void
	 */
	public static function autoload($class_name)
	{
		$tmp = explode('\\', $class_name);
		if (isset(self::$namespace_map[$tmp[0]])) {
			$space = array_shift($tmp);

			//convert class name to file name
			$file = implode('/', $tmp);

			//include class file
			include self::$namespace_map[$space] . $file . '.php';
		}
	}

	/**
	 * get all in type
	 *
	 * @param string $type
	 *
	 * @return mixed 	return the config,and false on error
	 */
	public static function get_config($type, $key)
	{
		//load
		$cache_key = $type;
		if (empty(self::$config_cache[$cache_key]) ) {
			if (strpos('/', $type) !== false || strpos('\\', $type) !== false) {
				throw new Exception("config '{$type}' is invalid", Errno::CONFIG_TYPE_INVALID);
			}

			//load from file
			$file = L_APP_PATH . 'conf/' . L_ENV . '.' . $type . '.php';
			self::$config_cache[$cache_key] = include $file;
			if (self::$config_cache[$cache_key] === false)	{
				throw new Exception("config {$type} not exists,file path:{$file}", Errno::CONFIG_TYPE_INVALID);
			}
		}

		//get all in type
		if (is_null($key)) {
			return self::$config_cache[$cache_key];
		}

		//get by key
		if (isset(self::$config_cache[$cache_key][$key])) {
			return self::$config_cache[$cache_key][$key];
		}

		//key not found
		throw new Exception("config key not found,type:{$type},key:{$key}", Errno::CONFIG_ITEM_INVALID);
	}

	/**
	 * get database instance by driver & config name
	 *
	 * @param string $driver
	 * @param string $name
	 * @param string $index
	 *
	 * @return Lavender\Db\Interface
	 */
	public static function get_database($driver, $name, $index = null)
	{
		$conf = self::get_config('db', $name);

		//if distributed
		if ($index !== null) {
			$conf['database'] .= $index;
		}

		$cache_key = "$driver|$name|$index";

		//check process cache
		if (isset(self::$db_instances[$cache_key])) {
			return self::$db_instances[$cache_key];
		}

		//create instance
		switch ($driver) {
			case 'mysql':
				$instance = new Db\Mysql($conf['host'], $conf['user'], $conf['password'], $conf['database'], $conf['port'], $conf['charset']);
				break;

			default:
				throw new Exception("database driver invalid,driver:{$driver}", Errno::PARAM_INVALID);
		}

		//return & cache to process
		return self::$db_instances[$cache_key] = $instance;
	}

	/**
	 * get language text by code
	 *
	 * @param int $code
	 *
	 * @return string
	 */
	public static function get_lang_text($code)
	{
		$lang = self::get_config('const', 'lang');

		if (empty(self::$language_cache)) {
			//load from file
			$file = L_APP_PATH . 'lang/' . $lang . '.php';
			self::$language_cache = include $file;
		}

		if (!isset(self::$language_cache[$code])) {
			trigger_error("language item \"{$code}\" undefined", E_USER_WARNING);
			return null;
		}

		return self::$language_cache[$code];
	}
}
