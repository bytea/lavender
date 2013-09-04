<?php
namespace Lavender\Dao;
use Lavender\Core;
use Lavender\Errno;

/**
 * key value table access class
 *
 */
abstract class KvTable
{
	const FLAG_SERIALIZE = 0x1;

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
	protected $table = 'kv_demo_table';

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
	 * @return KvTable
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
	 * get record data
	 * @param int $id
	 * @return array
	 */
	public function get($id)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$condition = is_numeric($id) ? "`id`={$id}" : "`id`='" . $this->db_handle->escape($id) . "'";
		$items = $this->db_handle->get($this->table, $condition);
		if (empty($items)) {
			return null;
		}

		$item = $items[0];
		if ($item['flags'] & self::FLAG_SERIALIZE) {
			$data = $this->unpack($item['data']);
			if ($data === false) {
				throw new Exception('unserialize failed,data:' . $item['data'], Errno::UNPACK_FAILED);
			}

			return $data;
		}

		return $item['data'];
	}

	/**
	 * get the record's raw data
	 * @param int $id
	 * @return array
	 */
	public function get_raw_record($id)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$condition = is_numeric($id) ? "`id`={$id}" : "`id`='" . $this->db_handle->escape($id) . "'";
		$items = $this->db_handle->get($this->table, $condition);
		if (empty($items)) {
			return null;
		}

		$item = $items[0];
		if ($item['flags'] & self::FLAG_SERIALIZE) {
			$data = $this->unpack($item['data']);
			if ($data === false) {
				throw new Exception('unserialize failed,data:' . $item['data'], Errno::UNPACK_FAILED);
			}

			$item['data'] = $data;
		}

		return $item;
	}

	/**
	 * insert an record
	 * @param array $record
	 * @return int
	 */
	public function add($id, $data, $time = 0)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$flags = 0;
		if (!is_string($data) && !is_int($data) && !is_float($data) ) {
			$flags |= self::FLAG_SERIALIZE;
			$data = $this->pack($data);
		}

		$record = array(
			'id' => $id,
			'flags' => $flags,
			'data' => $data,
			'updated' => $time ? $time : time(),
		);

		$this->db_handle->insert($this->table, $record);
	}

	/**
	 * insert or update a record
	 * @param int $id
	 * @param array $data
	 * @param int $updated
	 * @return int
	 */
	public function set($id, $data, $time = 0)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$flags = 0;
		if (!is_string($data) && !is_int($data) && !is_float($data) ) {
			$flags |= self::FLAG_SERIALIZE;
			$data = $this->pack($data);
		}

		$update_record = array(
			'flags' => $flags,
			'data' => $data,
			'updated' => $time ? $time : time()
		);

		$insert_record = array(
			'id' => $id,
			'flags' => $flags,
			'data' => $data,
			'updated' => $time ? $time : time()
		);

		$this->db_handle->insert_or_update($this->table, $insert_record, $update_record);
	}

	/**
	 * update a record
	 * @param string $id
	 * @param array $record
	 * @param int $time
	 * @return mixed  num on success,false on failed
	 */
	public function update($id, $data, $time = 0)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$flags = 0;
		if (!is_string($data) && !is_int($data) && !is_float($data) ) {
			$flags |= self::FLAG_SERIALIZE;
			$data = $this->pack($data);
		}

		$update_record = array(
			'flags' => $flags,
			'data' => $data,
			'updated' => $time ? $time : time(),
		);

		$id = $this->db_handle->escape($id);
		$this->db_handle->update($this->table, $update_record, "id='{$id}'");

		return $this->db_handle->get_affected_rows();
	}

	/**
	 * update the record modify time
	 * @param string $id
	 * @param int $time
	 * @return mixed updated num on success,false on failed
	 */
	public function update_time($id, $time = 0)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$update_record = array(
			'updated' => $time ? $time : time(),
		);

		$id = $this->db_handle->escape($id);
		$this->db_handle->update($this->table, $update_record, "id='{$id}'");

		return $this->db_handle->get_affected_rows();
	}

	/**
	 * delete record
	 * @param string $id
	 * @return mixed deleted num on success,false on failed
	 */
	public function delete($id)
	{
		if (is_null($id)) {
			throw new Exception('param error', Errno::PARAM_INVALID);
		}

		$id = $this->db_handle->escape($id);
		$this->db_handle->delete($this->table, "id='{$id}'");

		return $this->db_handle->get_affected_rows();
	}

	protected function pack($data)
	{
		//return serialize($data);
		return json_encode($data, JSON_UNESCAPED_UNICODE);
	}

	protected function unpack($data)
	{
		//return unserialize($data);
		return json_decode($data, true);
	}
}
