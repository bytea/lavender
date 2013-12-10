<?php
namespace Lavender\Dao;
use Lavender\Core;
use Lavender\Errno;

/**
 * distributed table access class
 *
 */
abstract class TableDistributed
{
	/**
	 * database system
	 * @var string
	 */
	protected $driver = 'mysql';

	/**
	 * database config name
	 * @var string
	 */
	protected $database = 'demo_database';

	/**
	 * table
	 * @var string
	 */
	protected $table = 'demo_table';

	/**
	 * index key field name
	 * @var string
	 */
	protected $first_key = 'id';

	/**
	 * distributed database count
	 * @var int
	 */
	protected $database_num = 1;

	/**
	 * distributed table count
	 * @var int
	 */
	protected $table_num = 1;

	/**
	 * use to cache query data
	 * @var array
	 */
	protected $single_cache = array();

	/**
	 * all instances cache
	 * @var array
	 */
	private static $instances = array();

	/**
	 * get instance singletion
	 *
	 * @return Table
	 */
	public static function instance()
	{
		$class = get_called_class();
		if (empty(self::$instances[$class]) ) {
			self::$instances[$class] = new $class();
		}

		return self::$instances[$class];
	}

	/**
	 * multipie get
	 *
	 * @param mixed $ids key list
	 * @param array $filter
	 * @param array $fields
	 *
	 * @return array
	 */
	public function get($ids, $fields = array(), array $filter = array(), $order = null, $offset = null, $length = null)
	{
		if (!empty($ids) && !is_array($ids) ) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//key is required
		if (!empty($fields) && !in_array($this->first_key, $fields) ) {
			$fields[] = $this->first_key;
		}

		//get items from all tables
		$items = array();
		$id_groups = $this->group_keys($ids);
		foreach ($id_groups as $db_id => $ids_groupby_table) {
			$db = Core::get_database($this->driver, $this->database, $db_id);

			$filter_condition = '';
			if (!empty($filter)) {
				$filter_condition = $this->build_condition($filter, $db);
			}

			foreach ($ids_groupby_table as $table => $_ids) {
				$condition = '';
				if (!empty($ids) ) {
					$ids = array_map('intval', $ids);
					$condition = "{$this->first_key} IN ('" . implode("','", $_ids) . "')";
				}
				if (!empty($filter_condition)) {
					$condition = $condition ? "{$condition} AND {$filter_condition}" : $filter_condition;
				}

				$items_in_table = $db->get($table, $condition, $fields, $order, $offset, $length);
				$items = array_merge($items, $items_in_table);
			}
		}

		return $items;
	}

	/**
	 * single record get
	 *
	 * @param mixed $id
	 * @param array $filter filter condition
	 *
	 * @return mixed
	 */
	public function get_single($id, array $filter = array() )
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//single
		if (isset($this->single_cache[$id])) {
			return $this->single_cache[$id];
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		//base condition
		$id = intval($id);
		$condition = "{$this->first_key}='{$id}'";

		//filter condition
		if (!empty($filter)) {
			$condition .= ' AND ' . $this->build_condition($filter, $db);
		}

		$items = $db->get($table, $condition);
		if (empty($items) ) {
			return null;
		}

		//cache to process cache
		$this->single_cache[$id] = $items[0];

		return $items[0];
	}

	/**
	 * insert record
	 *
	 * @param int $id
	 * @param array $record
	 *
	 * @return void
	 */
	public function add($id, $record)
	{
		if (!is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		if (!is_array($record)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//key is required & need number
		if (!is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		$record[$this->first_key] = $id;
		$db->insert($table, $record);
	}

	/**
	 * set record
	 *
	 * @param int $id
	 * @param array $record
	 *
	 * @return void
	 */
	public function set($id, $record)
	{
		if (!is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		if (empty($record) || !is_array($record)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		$record[$this->first_key] = $id;
		$db->insert_or_update($table, $record, $record);
	}

	/**
	 * update record
	 *
	 * @param int $id
	 * @param array $record
	 *
	 * @return int 	the affected rows
	 */
	public function update($id, $record, array $filter = array() )
	{
		if (!is_numeric($id)) {
			throw new Exception("param error,first_key's value invalid", Errno::PARAM_INVALID);
		}

		if (empty($record) || !is_array($record)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		//base condition
		$id = intval($id);
		$condition = "{$this->first_key}={$id}";

		//filter condition
		if (!empty($filter)) {
			foreach ($filter as $k => $v) {
				if (!$db->check_name($k)) {
					throw new Exception("filter field name verify failed,name:\'{$k}\'", Errno::PARAM_INVALID);
				}

				$v = $db->escape($v);
				$condition .= " AND `{$k}`='{$v}'";
			}
		}

		$db->update($table, $record, $condition);
		return $db->get_affected_rows();
	}

	public function delete($id, array $filter = array() )
	{
		if (!is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		//base condition
		$id = intval($id);
		$condition = "{$this->first_key}={$id}";

		//filter condition
		if (!empty($filter)) {
			foreach ($filter as $k => $v) {
				if (!$db->check_name($k)) {
					throw new Exception("filter field name verify failed,name:\'{$k}\'", Errno::PARAM_INVALID);
				}

				$v = $db->escape($v);
				$condition .= " AND `{$k}`='{$v}'";
			}
		}

		$db->delete($table, $condition);
		return $db->get_affected_rows();
	}

	/**
	 * increment to field
	 *
	 * @param int $id
	 * @param string $field
	 * @param int $num
	 *
	 * @return int 	the affected rows
	 */
	public function increment($id, $field, $num, array $filter = array() )
	{
		if (!is_numeric($id)) {
			throw new Exception("param error,first_key's value invalid", Errno::PARAM_INVALID);
		}

		if (empty($field) || !is_string($field)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		//base sql
		$sql = $db->format_sql("UPDATE {$table} SET `%n`=`%n`+%d WHERE {$this->first_key}=%d", $field, $field, $num, $id);

		//filter condition
		if (!empty($filter)) {
			foreach ($filter as $k => $v) {
				if (!$db->check_name($k)) {
					throw new Exception("filter field name verify failed,name:\'{$k}\'", Errno::PARAM_INVALID);
				}

				$v = $db->escape($v);
				$sql .= " AND `{$k}`='{$v}'";
			}
		}

		$db->execute($sql);
		return $db->get_affected_rows();
	}

	/**
	 * count
	 *
	 * @param mixed $id
	 * @param array $filter filter condition
	 *
	 * @return int
	 */
	public function count($id, array $filter = array() )
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		//get db instance
		$db = $this->get_database_instance($id);

		//get table
		$table = $this->get_table_name($id);

		//base condition
		$id = intval($id);
		$condition = "{$this->first_key}='{$id}'";

		//filter condition
		if (!empty($filter)) {
			foreach ($filter as $k => $v) {
				if (!$db->check_name($k)) {
					throw new Exception("filter field name verify failed,name:\'{$k}\'", Errno::PARAM_INVALID);
				}

				$v = $db->escape($v);
				$condition .= " AND `{$k}`='{$v}'";
			}
		}

		return $db->count($table, $condition);
	}

	protected function build_condition(array $filter, $db)
	{
		$items = '';
		foreach ($filter as $k => $v) {
			if (!$db->check_name($k)) {
				throw new Exception("filter field name verify failed,name:\'{$k}\'", Errno::PARAM_INVALID);
			}

			$v = $db->escape($v);
			$items[] = "`{$k}`='{$v}'";
		}

		return implode(' AND ', $items);
	}

	protected function get_database_instance($id)
	{
		$db_id = $this->get_database_id($id);
		return Core::get_database($this->driver, $this->database, $db_id);
	}

	protected function get_database_id($id)
	{
		return $this->database_num > 1 ? $id % $this->database_num : null;
	}

	protected function get_table_name($route_id)
	{
		if ($this->table_num <= 1) {
			return $this->table;
		}

		return $this->table . (intval($route_id / $this->database_num) % $this->table_num);
	}

	/**
	 * group keys by db & table for multipie get
	 *
	 * @param array $ids
	 *
	 * @return array
	 */
	protected function group_keys($ids)
	{
		$group = array();
		foreach ($ids as $id) {
			$db_idx = $this->get_database_id($id);
			$table = $this->get_table_name($id);

			if (!isset($group[$db_idx])) {
				$group[$db_idx] = array();
			}

			if (!isset($group[$db_idx][$table])) {
				$group[$db_idx][$table] = array();
			}

			$group[$db_idx][$table][] = $id;
		}

		return $group;
	}
}

