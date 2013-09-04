<?php
namespace Lavender;

class Session implements \ArrayAccess
{
	protected $key;
	protected $request_time;
	protected $timeout;
	protected $updated;
	protected $dao_name;

	protected $id;
	protected $secret;

	protected $data = array();
	protected $inited = false;
	protected $changed = false;

	public function __construct($key, $request_time, $timeout, $dao_name = null)
	{
		$this->key = $key;
		$this->request_time = $request_time;
		$this->timeout = $timeout;
		$this->dao_name = $dao_name;
	}

	public function __destruct()
	{
		$this->save_data();
	}

	public function is_valid()
	{
		if (!$this->inited) {
			$this->init();
		}

		return empty($this->data) ? false : true;
	}

	/**
	 * create new session
	 *
	 * @param int $id
	 * @param int $time
	 *
	 * @return string
	 */
	public function create($id, $time)
	{
		$this->id = $id;
		$this->data['secret'] = $this->secret = $this->make_secret($time);
		$this->changed = true;
		$this->inited = true;

		return $this->make_key();
	}

	/**
	 * destroy current session
	 *
	 * @return string
	 */
	public function destroy()
	{
		$this->data = array();
		$this->changed = true;
		$this->save_data();
	}

	protected function make_key()
	{
		if (!$this->id) {
			throw new Exception('session id undefined.', Errno::SESSION_ID_INVALID);
		}

		//signature code
		$signature = $this->make_signature();

		return "{$this->id}_{$this->secret}_{$signature}";
	}

	protected function make_secret($time)
	{
		return mt_rand(1000000000, 9999999999) . $time;
	}

	protected function make_signature()
	{
		//get hash key
		$hash_key = Core::get_config('const', 'hash_key');

		//signature code
		return substr(md5("{$this->id}|{$this->secret}|" . $hash_key), 5, 10);
	}

	protected function init()
	{
		if ($this->inited) {
			return $this->id;
		}

		$this->inited = true;

		//check session id & key
		if (empty($this->key) ) {
			return null;
		}

		//get session key info
		$tmp = explode('_', $this->key);
		$this->id = intval($tmp[0]);
		$this->secret = trim($tmp[1]);
		$signature = trim($tmp[2]);
		if (empty($this->secret) || empty($signature)) {
			throw new Exception\Auth('session key invalid.', Errno::SESSION_INVALID);
		}

		//check signature
		if ($this->make_signature() !== $signature) {
			throw new Exception\Auth('session key invalid.', Errno::SESSION_INVALID);
		}

		//read & check session data
		$this->data = $this->read_data();
		if (empty($this->data) ) {
			throw new Exception\Auth('session record not found on server.', Errno::SESSION_INVALID);
		}

		if (empty($this->data['secret']) || $this->data['secret'] != $this->secret) {
			throw new Exception\Auth('session secret verify failed.', Errno::SESSION_INVALID);
		}

		return $this->id;
	}

	protected function read_data()
	{
		if (!$this->id) {
			return array();
		}

		$item = $this->get_handle()->get_raw_record($this->id);
		if ($item && $this->timeout && $item['updated'] < ($this->request_time - $this->timeout) ) {
			throw new Exception\Auth('session timeout on server side.', Errno::SESSION_TIMEOUT);
		}

		$this->updated = $item['updated'];

		return $item ? $item['data'] : array();
	}

	protected function save_data()
	{
		if (!$this->id) {
			return true;
		}

		$dao = $this->get_handle();

		//destory
		if (empty($this->data) ) {
			return $dao->delete($this->id);
		}

		//update modify time only
		if (!$this->changed) {
			//update session time interval: 5min
			if ($this->request_time > $this->updated + 300) {
				return $dao->update_time($this->id, $this->request_time);
			}

			return true;
		}

		//update or add
		return $dao->set($this->id, $this->data, $this->request_time);
	}

	protected function get_handle()
	{
		//return Dao\SessionKvTable::instance();
		static $dao;
		if (!$dao) {
			$dao_name = $this->dao_name ? $this->dao_name : 'Dao\SessionKvTable';
			$dao = new $dao_name();
		}

		return $dao;
	}

	public function offsetSet($offset, $value)
	{
		if ($offset === null) {
			throw new Exception('key is empty.', Errno::PARAM_INVALID);
		}

		if (!$this->inited) {
			$this->init();
		}

		$this->changed = true;
		$this->data[$offset] = $value;
	}

	public function offsetUnset($offset)
	{
		if (!$this->inited) {
			$this->init();
		}

		$this->changed = true;
		unset($this->data[$offset]);
	}

	public function offsetExists($offset)
	{
		if (!$this->inited) {
			$this->init();
		}

		return isset($this->data[$offset]);
	}

	public function offsetGet($offset)
	{
		if (!$this->inited) {
			$this->init();
		}

		return isset($this->data[$offset]) ? $this->data[$offset] : null;
	}
}
