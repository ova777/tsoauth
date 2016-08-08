<?php
namespace ova777\TSOAuth;

/**
 * Вспомогательные статические функции
 * Class System
 * @package ova777\TSOAuth
 */
class System {

	/**
	 * Аналог Javascript-функции encodeURIComponent
	 * @param string $str
	 * @return string
	 */
	public static function encodeURIComponent($str) {
		$revert = array('%21'=>'!', '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')');
		return strtr(rawurlencode($str), $revert);
	}

	/**
	 * Аналог Javascript-функции encodeURI
	 * @param string $url
	 * @return string
	 */
	public static function encodeURI($url) {
		$unescaped = array(
			'%2D'=>'-','%5F'=>'_','%2E'=>'.','%21'=>'!', '%7E'=>'~',
			'%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'
		);
		$reserved = array(
			'%3B'=>';','%2C'=>',','%2F'=>'/','%3F'=>'?','%3A'=>':',
			'%40'=>'@','%26'=>'&','%3D'=>'=','%2B'=>'+','%24'=>'$'
		);
		$score = array(
			'%23'=>'#'
		);
		return strtr(rawurlencode($url), array_merge($reserved,$unescaped,$score));
	}

	/**
	 * Аналог php-функции print_r, выводит результат в html-формате
	 * @param mixed $data
	 * @param bool $return
	 * @return null|string
	 */
	public static function printRHTML($data, $return = false) {
		$str = print_r($data, true);
		$str = strtr($str, array(' ' => '&nbsp;', "\n" => '<br/>'));
		if($return) return $str;
		echo $str;
	}
}