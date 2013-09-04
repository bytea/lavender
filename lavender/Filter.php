<?php
namespace Lavender;

class Filter
{
	const T_RAW = 0x0;
	const T_STRING = 0x1;
	const T_INT = 0x2;
	const T_FLOAT = 0x4;
	const T_BOOL = 0x8;
	const T_MAP = 0x10;

	const T_STRIP_ER = 0x20; //strip \r
	const T_STRIP_NL = 0x40; //strip \n and \r
	const T_STRIP_TAGS = 0x80; //strip html tags

	const T_EMAIL = 0x1000;
	const T_URL = 0x2000;
	const T_PHONE = 0x4000;
	const T_MOBILE = 0x8000;

	/**
	 * filter from souce data by types
	 *
	 * @param $source source data list
	 * @param $definitions filter type list
	 * @param $prefix output keys prefix
	 *
	 * @return array
	 */
	public static function filter_map(array $source, array $definitions, $prefix = '')
	{
		$output = array();
		foreach ($definitions as $_k => $type) {
			if (!isset($source[$_k]) ) {
				continue;
			}
			$output[$prefix . $_k] = self::filter($source[$_k], $type);
		}

		return $output;
	}

	/**
	 * filter from souce data by type
	 *
	 * @param $var source data
	 * @param $type filter type list
	 *
	 * @return array
	 */
	public static function filter($var, $type)
	{
		if ($type === self::T_RAW) {
			return $var;
		}

		//map
		if ($type & self::T_MAP) {
			if (is_array($var) ) {
				$tmp_type = $type ^ self::T_MAP;
				if ($tmp_type) {
					//filter to every item
					foreach ($var as $tmp_key => $tmp_value) {
						$var[$tmp_key] = self::filter($tmp_value, $tmp_type);
					}
				}

				return $var;
			}

			if (!empty($var)) {
				return false;
			}

			return array();
		}

		//int
		if ($type & self::T_INT) {
			if (!is_numeric($var)) {
				return false;
			}

			$var = intval($var);
			return $var;
		}

		//float
		if ($type & self::T_FLOAT) {
			if (!is_numeric($var)) {
				return false;
			}

			$var = doubleval($var);
			return $var;
		}

		//boolean
		if ($type & self::T_BOOL) {
			$var = empty($var) ? 0 : 1;
			return $var;
		}

		//string above
		if ($type & self::T_STRING) {
			if (is_string($var)) {
				return $var;
			}

			return false;
		}

		if ($type & self::T_EMAIL) {
			$var = filter_var($var, FILTER_VALIDATE_EMAIL);
			if ($var === false) {
				return false;
			}

			return $var;
		}

		if ($type & self::T_URL) {
			$var = filter_var($var, FILTER_VALIDATE_URL);
			if ($var === false) {
				return false;
			}

			return $var;
		}

		if ($type & self::T_PHONE) {
			if (preg_match('/^(\+?[0-9]{2,3})?[0-9]{3,7}\-[0-9]{6,8}(\-[0-9]{2,6})?$/', $var)) {
				return false;
			}

			return $var;
		}

		if ($type & self::T_MOBILE) {
			if (preg_match('/^(\+?[0-9]{2,3})?[0-9]{6,11}$/', $var)) {
				return false;
			}

			return $var;
		}

		//strip \r
		if ($type & self::T_STRIP_ER) {
			$var = str_replace("\r", '', $var);
		}

		//strip \n & \r
		if ($type & self::T_STRIP_NL) {
			$var = str_replace("\r", '', $var);
			$var = str_replace("\n", '', $var);
		}

		//strip html tags
		if ($type & self::T_STRIP_TAGS) {
			$var = strip_tags($var);
		}

		return $var;
	}
}



