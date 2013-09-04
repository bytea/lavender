<?php
namespace Lavender\Db;
use Lavender\Errno;

/**
 * Mysql Operater Class
 * based on mysqli
 */
class Mysql
{
	private $host;
	private $username;
	private $password;
	private $database;
	private $port;
	private $charset;
	private $db_link;

	/**
	 * the last query sql
	 * @var int
	 */
	public $last_sql = '';

	public function __construct($host, $username, $password, $database, $port=null, $charset=null)
	{
		$this->host = $host;
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->port = $port;
		$this->charset = $charset;
	}

	public function __destruct()
	{
		if ($this->db_link) {
			mysqli_close($this->db_link);
			$this->db_link = null;
		}
	}

	/**
	 * connect & select database
	 * @return void
	 */
	public function open($reconnect = false)
	{
		if ($this->db_link && !$reconnect) {
			return ;
		}

		$this->db_link = mysqli_connect($this->host, $this->username, $this->password, $this->database, $this->port);
		if (!$this->db_link) {
			throw new Exception("database connect failed,code:" . mysqli_connect_errno() . ',msg:' . mysqli_connect_error(), Errno::DB_CONNECT_FAILED);
		}

		if ($this->charset) {
			$sql = "SET NAMES {$this->charset}";
			$result = mysqli_query($this->db_link, $sql);
			if (!$result) {
				throw new Exception("query failed,sql:[{$sql}],code:" . mysqli_errno($this->db_link) . ',msg:' . mysqli_error($this->db_link), Errno::DB_FAILED);
			}
		}
	}

	/**
	 * close databse link
	 */
	function close()
	{
		if ($this->db_link) {
			mysqli_close($this->db_link);
			$this->db_link = null;
		}
	}

	/**
	 * execute sql
	 *
	 * @param string $sql
	 *
	 * @return mixed
	 */
	function execute($sql)
	{
		$this->last_sql = $sql;
		$this->open();

		$result = mysqli_query($this->db_link, $sql);
		if (!$result) {
			//if (mysqli_errno($this->db_link) == 1062) { //key duplicated
				//return false;
			//}

			throw new Exception('execute sql failed,error:' . mysqli_error($this->db_link) . ',sql:[' . $sql . ']', Errno::DB_FAILED);
		}

		return $result;
	}

	/**
	 * select and return an array
	 *
	 * @param string $table
	 * @param string $condition
	 * @param string $fields
	 * @param string $order (eg. 'id DESC')
	 * @param int $offset limit offset
	 * @param int $length limit size
	 *
	 * @return array
	 */
	public function get($table, $condition, array $fields = array(), $order = null, $offset = null, $length = null)
	{
		if (!$this->check_name($table) ) {
			throw new Exception("table name invalid", Errno::PARAM_INVALID);
		}

		//check fields
		if (!empty($fields) ) {
			if (!is_array($fields)) {
				throw new Exception("param fields not an array", Errno::PARAM_INVALID);
			}

			foreach ($fields as $field) {
				if (!$this->check_name($field) ) {
					throw new Exception("field \"{$field}\" invalid", Errno::PARAM_INVALID);
				}
			}

			$fields = implode(',', $fields);
		}
		else {
			$fields = '*';
		}

		$condition = $condition ? " WHERE {$condition}" : '';
		$sql = "SELECT {$fields} FROM {$table}{$condition}";

		if ($order) {
			if (!preg_match('/^[a-z0-9_\., ]+$/i', $order)) {
				throw new Exception("order exp \"{$order}\" is invalid", Errno::PARAM_INVALID);
			}
			$sql .= " ORDER BY {$order}";
		}

		return $this->get_by_sql($sql, $offset, $length);
	}

	/**
	 * Select by sql and return array
	 *
	 * @param string $sql
	 * @param int $offset
	 * @param int $length
	 *
	 * @return array
	 */
	public function get_by_sql($sql, $offset = null, $length = null)
	{
		if ($offset !== null) {
			$offset = intval($offset);
			$sql .= $length === null ? " LIMIT {$offset}" : " LIMIT {$offset}," . intval($length);
		}

		$result = $this->execute($sql);
		if (!$result) {
			throw new Exception('execute sql failed,error:' . mysqli_error($this->db_link) . ',sql[' . $sql . ']', Errno::DB_FAILED);
		}

		$items = array();
		while ($item = mysqli_fetch_assoc($result) ) {
			$items[] = $item;
		}

		mysqli_free_result($result);

		return $items;
	}

	/**
	 * Select by sql and return MysqlRecordSet
	 *
	 * @param string $sql
	 * @param int $offset
	 * @param int $length
	 *
	 * @return MysqlRecordSet
	 */
	public function get_record_set_by_sql($sql, $offset = null, $length = null)
	{
		$result = $this->execute($sql);
		return new MysqlRecordSet($result);
	}

	/**
	 * Count records by condition
	 *
	 * @param string $condition
	 * @param string $field
	 *
	 * @return int
	 */
	public function count($table, $condition = '', $field = '*')
	{
		$condition = empty($condition) ? '' : ' WHERE ' . $condition;
		$sql = "SELECT COUNT({$field}) FROM {$table}{$condition}";

		$result = $this->execute($sql);
		$item = mysqli_fetch_row($result);
		mysqli_free_result($result);

		return intval($item[0]);
	}

	/**
	 * Insert record
	 *
	 * @param string $table
	 * @param array $record
	 *
	 * @return boolean
	 */
	public function insert($table, array $record)
	{
		$record = $this->escape_array($record);

		$insert_fields = array();
		$insert_values = array();
		foreach ($record as $k => $v) {
			if (!$this->check_name($k) ) {
				throw new Exception("field \"{$k}\" invalid", Errno::PARAM_INVALID);
			}
			$insert_fields[] = $k;
			$insert_values[] = $v;
		}

		$insert_fields = '`' . implode('`,`', $insert_fields) . '`';
		$insert_values = '\'' . implode('\',\'', $insert_values) . '\'';

		$sql = "INSERT INTO `$table`($insert_fields) VALUES($insert_values)";

		return $this->execute($sql);
	}

	/**
	 * insert or update record
	 * affected on MySQL 4.1 +, required primary key
	 *
	 * @param string $table
	 * @param array $record for insert
	 * @param array $update_record for update
	 *
	 * @return boolean
	 */
	public function insert_or_update($table, array $insert_record, array $update_record)
	{
		$insert_record = $this->escape_array($insert_record);
		$update_record = $this->escape_array($update_record);

		$insert_fields = array();
		$insert_values = array();
		foreach ($insert_record as $k => $v) {
			if (!$this->check_name($k) ) {
				throw new Exception("field \"{$k}\" invalid", Errno::PARAM_INVALID);
			}
			$insert_fields[] = $k;
			$insert_values[] = $v;
		}

		$update_items = array();
		foreach ($update_record as $k => $v) {
			if (!$this->check_name($k) ) {
				throw new Exception("field \"{$k}\" invalid", Errno::PARAM_INVALID);
			}
			$update_items[] = "`$k`='$v'";
		}

		$insert_fields = '`' . implode('`,`', $insert_fields) . '`';
		$insert_values = '\'' . implode('\',\'', $insert_values) . '\'';
		$update_items = implode(',', $update_items);

		$sql = "INSERT INTO `{$table}`({$insert_fields}) VALUES({$insert_values}) ON DUPLICATE KEY UPDATE {$update_items}";

		return $this->execute($sql);
	}

	/**
	 * Update
	 *
	 * @param string $table
	 * @param array $record
	 * @param string $condition
	 *
	 * @return boolean
	 */
	public function update($table, $record, $condition = '')
	{
		if (empty($condition)) {
			throw new Exception('condition is empty', Errno::PARAM_INVALID);
		}

		$record = $this->escape_array($record);

		$update_items = array();
		foreach ($record as $k => $v) {
			if (!$this->check_name($k) ) {
				throw new Exception("field \"{$k}\" invalid", Errno::PARAM_INVALID);
			}
			$update_items[] = "`$k`='$v'";
		}

		$update_items = implode(',', $update_items);
		$sql = "UPDATE `{$table}` SET {$update_items} WHERE {$condition}";

		return $this->execute($sql);
	}

	/**
	 * Delete
	 *
	 * @param string $table
	 * @param string $condition
	 *
	 * @return boolean
	 */
	public function delete($table, $condition)
	{
		if (empty($condition)) {
			throw new Exception('condition is empty', Errno::PARAM_INVALID);
		}

		$sql = "DELETE FROM `$table` WHERE $condition";
		return $this->execute($sql);
	}

	/**
	 * get last insert id
	 *
	 * @return int
	 */
	public function get_insert_id()
	{
		return mysqli_insert_id($this->db_link);
	}

	/**
	 * get affected rows
	 *
	 * @return int
	 */
	public function get_affected_rows()
	{
		return mysqli_affected_rows($this->db_link);
	}

	/**
	 * transaction start
	 *
	 * @return void
	 */
	public function start_transaction()
	{
		$this->execute('start transaction');
	}

	/**
	 * transaction commit
	 *
	 * @throw Exception
	 * @return void
	 */
	public function commit()
	{
		$this->execute('commit');
	}

	/**
	 * transaction rollback
	 *
	 * @return void
	 */
	public function rollback()
	{
		$this->execute('rollback');
	}

	/**
	 * get error code
	 * @return string
	 */
	public function get_errno()
	{
		if ($this->db_link) {
			return mysqli_errno($this->db_link);
		}

		return null;
	}

	/**
	 * get error message
	 * @return string
	 */
	public function get_error()
	{
		if ($this->db_link) {
			return mysqli_error($this->db_link);
		}

		return null;
	}

	/**
	 * escape sql string
	 * @param string $var
	 * @return string
	 */
	public function escape($var)
	{
		$this->open();
		return mysqli_real_escape_string($this->db_link, $var);
	}

	/**
	 * escape array
	 * @param string $items
	 * @return string
	 */
	public function escape_array($items)
	{
		$this->open();

		foreach ($items as $k => $v) {
			$items[$k] = mysqli_real_escape_string($this->db_link, $v);
		}

		return $items;
	}

	public function build_insert_string($item)
	{
		$item = $this->escape_array($item);

		$fields = array();
		$values = array();
		foreach ($item as $k => $v) {
			if (!$this->check_name($k) ) {
				throw new Exception("field \"{$k}\" invalid", Errno::PARAM_INVALID);
			}
			$fields[] = $k;
			$values[] = $v;
		}

		$fields = '`' . implode('`,`', $fields) . '`';
		$values = '\'' . implode('\',\'', $values) . '\'';

		return array(
			'fields' => $fields,
			'values' => $values,
		);
	}

	public function build_update_string($item)
	{
		$item = $this->escape_array($item);

		$tmp = array();
		foreach ($item as $k => $v) {
			if (!$this->check_name($k) ) {
				throw new Exception("field \"{$k}\" invalid", Errno::PARAM_INVALID);
			}
			$tmp[] = "`$k`='$v'";
		}

		return implode(',', $tmp);
	}

	/**
	 * check table name or field name
	 * @param string $string
	 * @return boolean
	 */
	public function check_name($string)
	{
		if (preg_match('/^[a-z0-9_]+$/i', $string) ) {
			return true;
		}

		return false;
	}

	/**
	 * format sql in a safty way
	 *
	 * @param string $format %bcdeufFosxXn
	 * @param string $argN
	 *
	 * @return string
	 */
	public function format_sql($format, $arg1, $argN)
	{
		if (empty($format) ) {
			throw new Exception('format string is empty', Errno::PARAM_INVALID);
		}

		$args = func_get_args();
		array_shift($args);

		$valid_exp = '/(?:%%|%(?:[0-9]+\$)?[+-]?(?:[ 0]|\'.)?-?[0-9]*(?:\.[0-9]+)?([bcdeufFosxXn]))/';
		if (!preg_match_all($valid_exp, $format, $matches)) {
			throw new Exception('format_sql() failed,no types in input', Errno::PARAM_INVALID);
		}
 		$types = $matches[1];

 		if (count($types) != count($args)) {
 			throw new Exception('_format_sql() failed,wrong parameter count', Errno::PARAM_INVALID);
 		}

 		$i = 0;
 		foreach ($types as $k => $type) {
 			switch ($type) {
 				//int
 				case 'b':
 				case 'c':
 				case 'd': //int
 				case 'u':
 				case 'o':
 				case 'x':
 				case 'X':
 					if (!is_numeric($args[$i]) || is_float($args[$i]) ) {
 						throw new Exception("format_sql() failed,value '{$args[$i]}' not match the type '%{$type}'.", Errno::PARAM_INVALID);
 					}
 					break;

 				//int or float
 				case 'e':
 				case 'f':
 				case 'F':
 					if (!is_numeric($args[$i])) {
 						throw new Exception("format_sql() failed,value '{$args[$i]}' not match the type '%{$type}'.", Errno::PARAM_INVALID);
 					}
 					break;

 				//string,need escape
 				case 's':
 					$args[$i] = $this->escape($args[$i]);
 					break;

 				//table or field name,need check
 				case 'n':
 					if (!$this->check_name($args[$i]) ) {
 						throw new Exception("format_sql() failed,value '{$args[$i]}' not match the type '%{$type}'.", Errno::PARAM_INVALID);
 					}

 					//set to 's',so it is can deal by vsprintf
 					$types[$k] = 's';
 					$format = str_replace('%n', '%s', $format);
 					break;

 				//remove the %%
 				case '':
 					unset($types[$k]);
 					continue;

 				default:
 					throw new Exception("format_sql() failed,type '{$type}' is invalid", Errno::PARAM_INVALID);

 			}

 			$i++;
 		}

 		return vsprintf($format, $args);
	}
}

/**
 * Record Set
 */
class MysqlRecordSet
{
	/**
	 * mysqli_result
	 * @var mysqli_result
	 */
	private $_result;

	/**
	 * init
	 * @param mysqli_result $statement
	 */
	public function __construct($result)
	{
		$this->result = $result;
	}

	public function __destruct()
	{
		$this->result && mysqli_free_result($this->result);
	}

	/**
	 * next record
	 *
	 * @return array
	 */
	function next()
	{
		return mysqli_fetch_assoc($this->result);
	}

	/**
	 * seek to the records pos
	 *
	 * @param int $offset
	 *
	 * @return boolean
	 */
	function seek($offset)
	{
		return mysqli_data_seek($this->result, $offset);
	}
}

