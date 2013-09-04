<?php
namespace Lavender;

class Validator
{
	public static function is_mobile($mobile)
	{
		$result = preg_match('/^(13|14|15|18)[0-9]{9}$/', $mobile);
		return $result ? true : false;
	}

	public static function is_phone($number)
	{
		$result = preg_match('/^(\+?[0-9]{2,3})?[0-9]{3,7}\-[0-9]{6,8}(\-[0-9]{2,6})?$/', $number);
		return $result ? true : false;
	}

	public static function is_email($email)
	{
		$result = filter_var($email, FILTER_VALIDATE_EMAIL);
		if ($result === false) {
			return false;
		}

		return true;
	}

	public static function is_utf8_zh($str)
	{
		$result = preg_match('/^[\x{4e00}-\x{9fa5}]+$/u', $str);
		return $result ? true : false;
	}

	/**
	 * check is a valid url
	 *
	 * @param string $url
	 * @param array $domains in domain list
	 *
	 * @return boolean
	 */
	public static function is_url($url, $domains = null)
	{
		$ret = filter_var($url, FILTER_VALIDATE_URL);
		if ($ret === false) {
			return false;
		}

		if (empty($domains) ) {
			return true;
		}

		//parse & check domain..

		$tmp = parse_url($url);

		//host not found
		if (empty($tmp['host'])) {
			return false;
		}

		$host = $tmp['host'];

		//fix host
		if (($pos = strpos($host, '#') ) ) {
			$host = substr($host, 0, $pos);
		}
		if (($pos = strpos($host, '?') ) ) {
			$host = substr($host, 0, $pos);
		}
		if (($pos = strpos($host, '\\') ) ) {
			$host = substr($host, 0, $pos);
		}

		//check domain
		foreach ($domains as $domain) {
			if ($domain == $host || substr($host, -(strlen($domain) + 1) ) == '.' . $domain) {
				return true;
			}
		}

		return false;
	}

	public static function is_ip($ip)
	{
		$ret = filter_var($ip, FILTER_VALIDATE_IP);
		if ($ret === false) {
			return false;
		}

		return true;
	}
}



