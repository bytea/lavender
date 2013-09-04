<?php
namespace Lavender\Dao;
use Lavender\Core;
use Lavender\Errno;

/**
 * table access class
 *
 */
abstract class Table
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
	 * first key field name
	 * @var string
	 */
	protected $first_key = 'id';

	/**
	 * use to cache query data
	 * @var array
	 */
	protected $single_cache = array();

	/**
	 * fields to type map
	 * @var array
	 */
	protected $field_definitions = array(
		//
	);

	protected $db_handle;

	/**
	 * all instances cache
	 * @var array
	 */
	private static $instances = array();

	public function __construct()
	{
		$this->db_handle = Core::get_database($this->driver, $this->database);
	}

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
	 * @param mixed $ids 	id or id list
	 * @param array $fields
	 * @param array $filter
	 * @param string $order
	 * @param int $offset
	 * @param int $length
	 *
	 * @return array
	 */
	public function get($ids, $fields = array(), array $filter = array(), $order = null, $offset = null, $length = null)
	{
		$condition = $this->build_condition($ids, $filter);
		return $this->db_handle->get($this->table, $condition, $fields, $order, $offset, $length);
	}

	/**
	 * single record get
	 *
	 * @param int $id
	 * @param array $filter filter condition
	 * @param string $order
	 *
	 * @return mixed
	 */
	public function get_single($id, array $filter = array(), $order = null)
	{
		if ($id !== null && !is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		if (is_null($id) && empty($filter)) {
			throw new Exception("id and filter is empty", Errno::PARAM_INVALID);
		}

		//single
		if (isset($this->single_cache[$id])) {
			return $this->single_cache[$id];
		}

		$condition = $this->build_condition($id, $filter);
		$items = $this->db_handle->get($this->table, $condition, array(), $order, 1);
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
	public function add($id, array $record)
	{
		if (!is_array($record)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		if ($id !== null) {
			if (!is_numeric($id)) {
				throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
			}
			$record[$this->first_key] = $id;
		}

		$this->db_handle->insert($this->table, $record);
	}

	/**
	 * set to a record
	 *
	 * @param int $id
	 * @param array $record
	 *
	 * @return void
	 */
	public function set($id, array $record)
	{
		if (empty($record) || !is_array($record)) {
			throw new Exception('param "$record" is empty', Errno::PARAM_INVALID);
		}

		if ($id !== null) {
			if (!is_numeric($id)) {
				throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
			}
			$record[$this->first_key] = $id;
		}

		$this->db_handle->insert_or_update($this->table, $record, $record);
	}

	/**
	 * update a record
	 *
	 * @param int $id
	 * @param array $record
	 * @param array $filter
	 *
	 * @return int 	the affected rows
	 */
	public function update($id, $record, array $filter = array() )
	{
		if ($id !== null && !is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		$condition = $this->build_condition($id, $filter);
		$this->db_handle->update($this->table, $record, $condition);

		return $this->db_handle->get_affected_rows();
	}

	/**
	 * delete
	 *
	 * @param int $id
	 * @param array $filter
	 *
	 * @return int 	the affected rows
	 */
	public function delete($id, array $filter = array() )
	{
		if ($id !== null && !is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		$condition = $this->build_condition($id, $filter);
		$this->db_handle->delete($this->table, $condition);

		return $this->db_handle->get_affected_rows();
	}

	/**
	 * increment to field
	 *
	 * @param int $id
	 * @param string $field
	 * @param int $num
	 * @param array $filter
	 *
	 * @return int 	the affected rows
	 */
	public function increment($id, $field, $num, array $update_item = array())
	{
		if (!$id || !is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		//check field
		if (empty($field) || !is_string($field) || !$this->db_handle->check_name($field) ) {
			throw new Exception('param error,field ({$field}) invalid', Errno::PARAM_INVALID);
		}

		if ($num >= 0) {
			//build insert item
			$insert_item = $update_item;
			$insert_item[$field] = $num;
			if (!is_null($id) ) {
				$insert_item[$this->first_key] = $id;
			}

			$insert = $this->db_handle->build_insert_string($insert_item);

			//base sql
			$sql = "INSERT INTO `{$this->table}` ({$insert['fields']}) VALUES ({$insert['values']}) ON DUPLICATE KEY UPDATE `{$field}` = `{$field}` + $num";

			//update item
			if (!empty($update_item)) {
				$update = $this->db_handle->build_update_string($update_item);
				$sql .= ",{$update}";
			}
		}
		else {
			$sql = "UPDATE `{$this->table}` SET `{$field}` = `{$field}` + $num";

			//update item
			if (!empty($update_item)) {
				$update = $this->db_handle->build_update_string($update_item);
				$sql .= ",{$update}";
			}

			$sql .= " WHERE id=" . intval($id);
		}

		$this->db_handle->execute($sql);
		return $this->db_handle->get_affected_rows();
	}

	/**
	 * count
	 *
	 * @param int $id
	 * @param array $filter filter condition
	 *
	 * @return int
	 */
	public function count($id, array $filter = array() )
	{
		if ($id !== null && !is_numeric($id)) {
			throw new Exception("first_key's value invalid", Errno::PARAM_INVALID);
		}

		$condition = $this->build_condition($id, $filter);
		return $this->db_handle->count($this->table, $condition);
	}

	/**
	 * build conditon by id & filter
	 *
	 * @param int $id
	 * @param array $filter
	 *
	 * @return string
	 */
	protected function build_condition($id, array $filter)
	{
		$condition_items = array();

		if ($id !== null) {
			if (is_array($id)) {
				$ids = array_map('intval', $id);
				$condition_items[] = "`{$this->first_key}` IN ('" . implode("','", $ids) . "')";
			}
			else {
				$condition_items[] = "`{$this->first_key}`=" . intval($id);
			}
		}

		if (!empty($filter)) {
			foreach ($filter as $k => $v) {
				if (!$this->db_handle->check_name($k)) {
					throw new Exception("filter field name verify failed,name:\'{$k}\'", Errno::PARAM_INVALID);
				}

				$v = $this->db_handle->escape($v);
				$condition_items[] = "`{$k}`='{$v}'";
			}
		}

		return implode(' AND ', $condition_items);
	}
}

