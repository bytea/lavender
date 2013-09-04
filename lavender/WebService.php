<?php
namespace Lavender;
use Lavender\Errno;

class WebService extends WebPage
{
	//const CONTENT_TYPE = 'text/json';

	public function before_execute()
	{
		$token = $this->get_cookie(self::TOKEN_KEY);
		$token2 = '';
		if (isset($_GET['token'])) {
			$token2 = $_GET['token'];
		}
		elseif (isset($_POST['token'])) {
			$token2 = $_POST['token'];
		}

		if ($token && $token != $token2) {
			throw new Exception("token verify failed", Errno::TOKEN_VERIFY_FAILED);
		}
	}

	protected function create_session()
	{
		if (isset($_COOKIE[self::SESSION_KEY_NAME])) {
			$key = $_COOKIE[self::SESSION_KEY_NAME];
		}
		else {
			$key = isset($_REQUEST[self::SESSION_KEY_NAME]) ? $_REQUEST[self::SESSION_KEY_NAME] : null;
		}

		$timeout = isset($this->options['session_timeout']) ? $this->options['session_timeout'] : 0;
		$dao_name = isset($this->options['session_dao']) ? $this->options['session_dao'] : '';

		return new Session($key, $this->get_request_time(), $timeout, $dao_name);
	}

	public function render($__data__ = null, $__view__ = null)
	{
		//get friend message
		if (isset($__data__['code']) && $__data__['code'] > 0) {
			$__data__['msg'] = Core::get_lang_text($__data__['code']);
		}

		//json
		$response = json_encode($__data__, JSON_UNESCAPED_UNICODE);

		if (!empty($_GET['callback']) ) {
			//jsonp
			if (!preg_match('/^[a-z0-9\._]+$/i', $_GET['callback']) ) {
				throw new Exception("callback error,callback:{$_GET['callback']}", Errno::PARAM_INVALID);
			}

			$response = $_GET['callback'] . "($response)";

			//for iframe submit mode
			if (substr($_GET['callback'], 0, 7) == 'parent.') {
				header('Content-Type: text/html; charset=utf-8');
				$response = "<script type='text/javascript'>$response</script>";
			}
		}

		echo $response;
	}
}
