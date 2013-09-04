<?php
namespace Lavender;

class WebPage
{
	/**
	 * request methods
	 * @const
	 */
	const M_GET = 1;
	const M_POST = 2;
	const M_COOKIE = 3;
	const M_FILE = 4;
	const M_ENV = 5;
	const M_SERVER = 6;

	/**
	 * session cookie name
	 * @const
	 */
	const SESSION_KEY_NAME = 'sk';
	const TOKEN_KEY = 'token';

	const CONTENT_TYPE = 'text/html';

	public static $instance;

	/**
	 * session data
	 * @var Session
	 */
	public $session;

	//mvc options
	public $options;

	//actions don't need login
	protected $without_auth_actions = array();

	//actions don't need verify token
	protected $without_token_actions = array();

	//current module & action
	protected $module_name;
	protected $action_name;

	public function __construct($module_name = null, $options = array())
	{
		$this->module_name = $module_name;
		$this->options = $options;
	}

	/**
	 * run the web application
	 *
	 * @param array $web_options
	 *
	 * @return void
	 */
	public static function run($web_options)
	{
		if (!is_array($web_options['action_modules']) ) {
			throw new Exception('action_modules not setted in $options', Errno::PARAM_INVALID);
		}

		//get route module & action
		$route_options = self::get_route_options();
		$module_name = empty($route_options['module']) ? 'index' : strtolower($route_options['module']);
		$action_name = empty($route_options['action']) ? 'index' : strtolower($route_options['action']);

		//module not found
		if (!in_array($module_name, $web_options['action_modules'])) {
			$web = new WebPage();
			$web->header_notfound();
			echo "Action module not found.\n";
			return ;
		}

		//execute
		$class_name = "App\\Action\\{$module_name}";
		self::$instance = new $class_name($module_name, $web_options);
		self::$instance->execute($action_name);
	}

	public static function get_route_options()
	{
		$m = empty($_GET['action']) ? '' : $_GET['action'];
		$m = strpos($m, '/') ? explode('/', $m) : explode('.', $m);

		return array(
			'module' => trim($m[0]),
			'action' => empty($m[1]) ? '' : trim($m[1]),
		);
	}

	public function before_execute()
	{
		//token, for csrf
		if (!$this->get_cookie(self::TOKEN_KEY)) {
			$this->set_cookie(self::TOKEN_KEY, $this->make_token() );
		}
	}

	public function execute($action_name)
	{
		$this->header_content_type(static::CONTENT_TYPE);

		try {
			$this->before_execute();

			$this->action_name = $action_name;
			$action_method = "{$action_name}_action";

			//action not found
			if (!method_exists($this, $action_method)) {
				$this->header_notfound();
				return ;
			}

			//create session
			$this->session = $this->create_session();

			//need auth?
			if (empty($this->without_auth_actions) || (array_search($action_name, $this->without_auth_actions) === false && array_search('*', $this->without_auth_actions) === false) ) {
				//check auth
				if (!$this->session->is_valid() ) {
					throw new Exception\Auth('auth verify failed', Errno::AUTH_FAILED);
				}
			}

			//call action
			$data = $this->$action_method();

			//render
			return $this->render($data);
		}
		catch (Exception\Auth $e) {
			$this->set_cookie(self::SESSION_KEY_NAME, '');
			return $this->render_auth_error($e->getMessage(), $e->getCode());
		}
		catch (Exception $e) {
			return $this->render_error($e->getMessage(), $e->getCode());
		}
	}

	/**
	 * render view
	 *
	 * @param mixed $data 	gdata/view is the system key,if set 'view',will render to this view
	 *
	 * @return void
	 */
	public function render($__data__ = null)
	{
		//get friend message
		if (isset($__data__['code']) && $__data__['code'] > 0) {
			$__data__['msg'] = Core::get_lang_text($__data__['code']);
		}

		//extract data
		if ($__data__) {
			if (!is_array($__data__)) {
				throw new Exception("Data to render not an array", Errno::PARAM_INVALID);
			}

			extract($__data__);
		}

		//global data in view
		$gdata = [
			'module' => $this->module_name,
			'action' => $this->action_name,
			'session' => $this->session,
			'options' => $this->options,
			'instance' => $this,
		];

		//set view
		if (empty($view)) {
			if (!empty($code)) {
				$view = 'common/error';
			}
			else {
				$view = $this->module_name . '/' . $this->action_name;
			}
		}

		//bind
		if (!include(L_APP_PATH . "view/{$view}.php") ) {
			throw new Exception("View not found:{$view}", Errno::FILE_NOTFOUND);
 		}
	}

	protected function render_error($msg, $code = -100)
	{
		$data = array(
			'code' => $code,
			'msg' => $msg,
			'view' => 'common/error',
		);

		return $this->render($data);
	}

	protected function render_auth_error($msg, $code = 401)
	{
		$data = array(
			'code' => $code,
			'msg' => $msg,
			'view' => 'common/error',
		);

		return $this->render($data);
	}

	protected function success($msg = '', $data = null)
	{
		return array(
			'code' => 0,
			'msg' => $msg,
			'data' => $data,
		);
	}

	protected function error($msg, $code = -1)
	{
		return array(
			'code' => $code,
			'msg' => $msg,
		);
	}

	protected function make_token()
	{
		return mt_rand(1000000000, 9999999999);
	}

	/**
	 * get request parameters
	 *
	 * @param array $definition {key1:filter1, key2:filter2, ... }
	 * @param int $method request method, self::M_GET / self::M_POST / self::M_COOKIE / self::M_REQUEST / self::M_FILE / self::M_ENV / self::M_SERVER
	 * @param boolean $required
	 * @param string $prefix
	 *
	 * @return void
	 */
	protected function parameters($definition, $method = WebPage::M_GET, $required = false, $prefix = null)
	{
		switch ($method) {
			case null:
			case self::M_GET:
				$source = $_GET;
				break;

			case self::M_POST:
				$source = $_POST;
				break;

			case self::M_COOKIE:
			 	$source = $_COOKIE;
			 	break;

			 case self::M_REQUEST:
			 	$source = $_REQUEST;
			 	break;

			case self::M_FILE:
				$source = $_FILES;
				break;

			case self::M_ENV:
				$source = $_ENV;
				break;

			case self::M_SERVER:
				$source = $_SERVER;
				break;

			default:
				return false;
		}

		$parameters = array();
		foreach ($definition as $key => $filter) {
			if (isset($source[$key]) ) {
				$result = Filter::filter($source[$key], $filter);
				if ($result === false) {
					throw new Exception\Filter("Parameter '{$key}' is invalid", Errno::INPUT_PARAM_INVALID);
				}
			}
			else {
				if ($required) {
					throw new Exception\Filter("Parameter '{$key}' is required", Errno::INPUT_PARAM_MISSED);
				}
				continue;
			}

			//parameter key prefix
			if ($prefix) {
				$key =  $prefix . $key;
			}

			$parameters[$key] = $result;
		}

		return $parameters;
	}

	protected function create_session()
	{
		$key = $this->get_cookie(self::SESSION_KEY_NAME);
		$timeout = isset($this->options['session_timeout']) ? $this->options['session_timeout'] : 0;
		$dao_name = isset($this->options['session_dao']) ? $this->options['session_dao'] : '';

		return new Session($key, $this->get_request_time(), $timeout, $dao_name);
	}

	public function get_current_url()
	{
		$protocol = empty($_SERVER['HTTPS']) ? 'http' : 'https';
		return $protocol . '://' . getenv('HTTP_HOST') . getenv('REQUEST_URI');
	}

	public function get_client_ip()
	{
		if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknow')) {
			$ip = getenv('HTTP_CLIENT_IP');
		} elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknow')) {
			$ip = getenv('HTTP_X_FORWARDED_FOR');
		} elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknow')) {
			$ip = getenv('REMOTE_ADDR');
		} elseif (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'],'unknow')){
			$ip = $_SERVER['REMOTE_ADDR'];
		} else {
			$ip = '0.0.0.0';
		}

		return $ip;
	}

	public function get_request_time()
	{
		return $_SERVER['REQUEST_TIME'];
	}

	public function get_cookie($name)
	{
		return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
	}

	public function set_cookie($name, $value, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = false)
	{
		return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
	}

	public function url($module, $action, array $parameters = array(), $protocol = 'http')
	{
		//set default to /
		empty($this->options['uri_path']) && $this->options['uri_path'] = '/';

		$url = "{$protocol}://{$this->options['domain']}{$this->options['uri_path']}/?action={$module}.{$action}";

		if (!empty($parameters)) {
			$url .= '&' . http_build_query($parameters);
		}

		return $url;
	}

	public function redirect($uri, $http_response_code = null)
	{
		header('Location: ' . $uri, true, $http_response_code);
	}

	public function header_content_type($type = 'text/html', $charset = 'utf-8')
	{
		header("Content-type:{$type}; charset={$charset}");
	}

	public function header_nocache()
	{
		header('Expires: Wed, 11 Jan 1980 05:00:00 GMT');
		header('Cache-Control: no-cache, must-revalidate, max-age=0');
		header('Pragma: no-cache');
	}

	public function header_modified($timestamp)
	{
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $timestamp) . ' GMT');
	}

	public function header_notfound()
	{
		$this->header_status(404);
	}

	public function header_status($code, $replace = true)
	{
		$headers = array(
			100 => 'Continue',
			101 => 'Switching Protocols',

			200 => 'OK',
			201 => 'Created',
			202 => 'Accepted',
			203 => 'Non-Authoritative Information',
			204 => 'No Content',
			205 => 'Reset Content',
			206 => 'Partial Content',

			300 => 'Multiple Choices',
			301 => 'Moved Permanently',
			302 => 'Found',
			303 => 'See Other',
			304 => 'Not Modified',
			305 => 'Use Proxy',
			307 => 'Temporary Redirect',

			400 => 'Bad Request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not Found',
			405 => 'Method Not Allowed',
			406 => 'Not Acceptable',
			407 => 'Proxy Authentication Required',
			408 => 'Request Timeout',
			409 => 'Conflict',
			410 => 'Gone',
			411 => 'Length Required',
			412 => 'Precondition Failed',
			413 => 'Request Entity Too Large',
			414 => 'Request-URI Too Long',
			415 => 'Unsupported Media Type',
			416 => 'Requested Range Not Satisfiable',
			417 => 'Expectation Failed',

			500 => 'Internal Server Error',
			501 => 'Not Implemented',
			502 => 'Bad Gateway',
			503 => 'Service Unavailable',
			504 => 'Gateway Timeout',
			505 => 'HTTP Version Not Supported'
		);

		if (!isset($headers[$code] ) ) {
			return false;
		}

		if (empty($_SERVER["SERVER_PROTOCOL"]) || ('HTTP/1.1' != $_SERVER["SERVER_PROTOCOL"] && 'HTTP/1.0' != $_SERVER["SERVER_PROTOCOL"]) ) {
			$_SERVER["SERVER_PROTOCOL"] = 'HTTP/1.0';
		}

		header("{$_SERVER["SERVER_PROTOCOL"]} {$code} {$headers[$code]}", $replace, $code);
		return true;
	}
}
