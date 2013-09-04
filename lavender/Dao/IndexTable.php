<?php
namespace Lavender\Dao;
use Lavender\Core;
use Lavender\Errno;

/**
 * index table object
 *
 */
abstract class IndexTable
{
	const VERSION = 1;

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
	protected $table = 'idx_demo_table';

	/**
	 * current record id
	 * @var int
	 */
	protected $id;

	/**
	 * current record data
	 * @var array
	 */
	protected $index_data;

	/**
	 * data columns
	 * the first column is pk
	 * @var int
	 */
	protected $columns = 2;

	/**
	 * the order column
	 * 0 to $columns - 1
	 * @var int
	 */
	protected $order_column = 1;

	/**
	 * all instances cache
	 * @var array
	 */
	private static $instances = array();

	public function __construct($id)
	{
		if ($this->order_column >= $this->columns) {
			throw new Exception("order colum is over then columns", Errno::DEFINED_INVALID);
		}

		if (!is_numeric($id)) {
			throw new Exception("param invalid,id:{$id}", Errno::PARAM_INVALID);
		}

		$this->id = $id;
		$this->read_data();
	}

	/**
	 * get instance singletion
	 *
	 * @return Lavender\Dao\IndexTable
	 */
	public static function instance($id)
	{
		$class = get_called_class();
		$key = $class . $id;
		if (empty(self::$instances[$key]) ) {
			self::$instances[$key] = new $class($id);
		}

		return self::$instances[$key];
	}

	public function get_total()
	{
		return count($this->index_data[0]);
	}

	public function get($offset = null, $length = null)
	{
		if ($offset === null) {
			return $this->index_data;
		}

		$items = array();
		for ($i = 0; $i < $this->columns; $i++) {
		 	$tmp_column = array_slice($this->index_data[$i], $offset, $length);

		 	//change to record order
		 	foreach ($tmp_column as $k => $v) {
		 		if (!isset($items[$k]) ) {
		 			$items[$k] = array();
		 		}

		 		$items[$k][$i] = $v;
		 	}
		}

		return $items;
	}

	public function get_column($column, $offset = null, $length = null)
	{
		if ($column >= $this->columns) {
			throw new Exception("column over range", Errno::PARAM_INVALID);
		}

		if ($offset === null) {
			return $this->index_data[$column];
		}

		return array_slice($this->index_data[$column], $offset, $length);
	}

	/**
	 * append record to index
	 * need call commit() method to save
	 *
	 * @param int $column0
	 * @param int $column1
	 *
	 * @return void
	 */
	public function append()
	{
		$num_args = func_num_args();
		$item = func_get_args();

		if ($num_args !== $this->columns) {
			throw new Exception("append item columns not match", Errno::PARAM_INVALID);
		}

		foreach ($item as $k => $v) {
			if (is_null($v) ) {
				throw new Exception("append column value invalid,columns:" . json_encode($item), Errno::PARAM_INVALID);
			}
		}

		//insert by order desc
		$count = count($this->index_data[$this->order_column]);
		if ($count == 0) {
			//source is empty
			//add each columns
			for ($i = 0; $i < $this->columns; $i++) {
				$this->index_data[$i][] = $item[$i];
			}
		}
		elseif ($this->index_data[$this->order_column][$count - 1] > $item[$this->order_column]) {
			//append to end
			for ($i = 0; $i < $this->columns; $i++) {
				array_push($this->index_data[$i], $item[$i]);
			}
		}
		else {
			//insert at front of the lower
			$offset = 0;
			while ($offset < $count) {
				//key repeated
				if ($item[0] == $this->index_data[0][$offset]) {
					break;
				}

				//order desc,big at the front
				if ($this->index_data[$this->order_column][$offset] < $item[$this->order_column]) {
					//insert each columns
					for ($i = 0; $i < $this->columns; $i++) {
						array_splice($this->index_data[$i], $offset, 0, $item[$i]);
					}

					break;
				}

				$offset++;
			}
		}
	}

	/**
	 * remove records from index
	 * need call commit() method to save
	 *
	 * @param array $column
	 *
	 * @return void
	 */
	public function remove_by_column($column, $remove_value, $limit = null)
	{
		if (!is_int($remove_value)) {
			throw new Exception("remove_value ({$remove_value}) invalid", Errno::PARAM_INVALID);
		}

		$total = count($this->index_data[$column]);
		$count = 0;
		for ($k = 0; $k < $total; $k++) {
			if ($this->index_data[$column][$k] == $remove_value) {
				//remove in every columns
				for ($i = 0; $i < $this->columns; $i++) {
					array_splice($this->index_data[$i], $k, 1);
				}

				$count++;
				if ($limit && $count >= $limit) {
					break;
				}

				//item removed,so
				$total--;
				$k--;
			}
		}
	}

	/**
	 * clean all records in index
	 * need call commit() method to save
	 *
	 * @return void
	 */
	public function clean()
	{
		$this->index_data = array();
	}

	/**
	 * commit data
	 * commit current index record to database
	 *
	 * @param int $time
	 *
	 * @return void
	 */
	public function commit($time = 0)
	{
		$record = array(
			'id' => $this->id,
			'data' => $this->pack($this->index_data),
			'updated' => $time ? $time : time()
		);

		$db = Core::get_database($this->driver, $this->database);
		$db->insert_or_update($this->table, $record, $record);
	}

	/**
	 * destroy current id index
	 * delete current index record from database
	 *
	 * @return void
	 */
	public function destroy()
	{
		$db = Core::get_database($this->driver, $this->database);
		$db->delete($this->table, 'id='  . intval($this->id) );
	}

	/**
	 * read current id index from database
	 *
	 * @return void
	 */
	protected function read_data()
	{
		$db = Core::get_database($this->driver, $this->database);

		$condition = '`id`=' . intval($this->id);
		$items = $db->get($this->table, $condition);
		if (empty($items)) {
			$data = array();
			for ($i = 0; $i < $this->columns; $i++) {
				$data[] = array();
			}
		}
		else {
			$data = $this->unpack($items[0]['data']);
		}

		$this->index_data = $data;
	}

	protected function pack(array $data)
	{
		$buffer = '';
		for ($i = 0; $i < $this->columns; $i++) {
			foreach ($data[$i] as $v) {
				$buffer .= pack('N', $v);
			}
		}

		$rows = count($data[0]);
		return pack('nnn', self::VERSION, $this->columns, $rows) . $buffer;
	}

	protected function unpack($buffer)
	{
		//default data
		if (empty($buffer)) {
			$data = array();
			for ($i = 0; $i < $this->columns; $i++) {
				$data[] = array();
			}

			return $data;
		}

		$header = unpack('nversion/ncolumns/nrows', $buffer);
		if ($header === false) {
			throw new Exception('header unpack failed', Errno::UNPACK_FAILED);
		}

		if ($header['version'] !== self::VERSION) {
			throw new Exception("header version invalid", Errno::UNPACK_FAILED);
		}

		//check length
		if (strlen($buffer) != (6 + $header['columns'] * $header['rows'] * 4) ) {
			throw new Exception('buffer length invalid', Errno::UNPACK_FAILED);
		}

		//unpack each columns
		$data = array();
		$buffer = substr($buffer, 6);
		for ($i = 0; $i < $header['columns']; $i++) {
			$values = unpack('N*', substr($buffer, 0, $header['rows'] * 4) );
			$buffer = substr($buffer, $header['rows'] * 4);
			$data[$i] = array_values($values);
		}

		//set default columns data
		if ($header['columns'] < $this->columns) {
			for ($i = $header['columns']; $i < $this->columns; $i++) {
				$data[$i] = array_fill(0, $header['rows'], null);
			}
		}

		return $data;
	}
}

