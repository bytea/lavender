<?php
namespace Lavender;

/**
 * Id Make Factory
 */
class IdMaker
{
	private static $cache = array();

	public static function make($key, $num = 2)
	{
		if (!isset(self::$cache[$key])) {
			$options = Core::get_config('id_maker', $key);
			if (!isset($options['maker']) ) {
				throw new Exception('miss option "maker"', 1);
			}

			if (!isset($options['name']) ) {
				throw new Exception('miss option "name"', 1);
			}

			if (!isset($options['start'])) {
				throw new Exception('miss option "start"', 1);
			}

			if ($num < 1) {
				$num = 1;
			}

			//call maker
			$maker = call_user_func(array($options['maker'], 'instance'));
			$result = $maker->make($options['name'], $num, $options['start']);
			if ($result === false) {
				throw new Exception("IdMaker::make({$key}, {$num}) failed");
			}

			self::$cache[$key] = array(
				'id' => $result,
				'buffer_num' => $num,
			);
		}

		$id = self::$cache[$key]['id'];
		self::$cache[$key]['id']++;
		self::$cache[$key]['buffer_num']--;

		if (self::$cache[$key]['buffer_num'] <= 0) {
			unset(self::$cache[$key]);
		}

		return $id;
	}
}
