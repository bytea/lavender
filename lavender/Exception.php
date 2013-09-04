<?php
namespace Lavender;

class Exception extends \Exception
{
	private static $exception_items = array();

	public function __construct($message = '', $code = 0)
	{
		$date_time = date('Y-m-d H:i:s');
		$file = str_replace(L_WORKSPACE_PATH, '', $this->getFile());
		$line = $this->getLine();
		$trace = str_replace(L_WORKSPACE_PATH, '', $this->getTraceAsString());

		self::$exception_items[] = "$date_time\t{$file}({$line})\t$code\t$message\n$trace";
		if (count(self::$exception_items) > 10) {
			self::flush();
		}

		parent::__construct($message, $code);
	}

	public static function flush()
	{
		if (empty(self::$exception_items)) {
			return ;
		}

		$old_mask = umask(0);

		//check & make dir
		$dir = L_WORKSPACE_PATH . 'log/' . L_APP_NAME . '/';
		if (!is_dir($dir)) {
			mkdir($dir, 0777, true);
		}

		//write file
		$file = $dir . date('Ymd');
		$contents = implode("\n", self::$exception_items) . "\n";
		file_put_contents($file . '.log', $contents, FILE_APPEND | LOCK_EX);

		//keep small than 1G
		if (filesize($file . '.log') > 1000000000) {
			rename($file . '.log', $file . '.' . date('His') . '.log');
		}

		umask($old_mask);

		//clear
		self::$exception_items = array();
	}
}

register_shutdown_function(array('\\Lavender\\Exception', 'flush') );

