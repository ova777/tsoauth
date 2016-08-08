<?php
namespace ova777\TSOAuth;

/**
 * Выполнение HTTP/HTTPS POST/PUT/GET/DELETE запросов с поддержкой basic авторизации, редиректов и cookies
 * Class Request
 * @package ova777\TSOAuth
 */
class Request {
	public $rawHeaders;
	public $headers;
	public $assocHeaders;
	public $body;
	public $cookies;
	public $httpCode;
	public $httpStatus;

	/**
	 * Выполнение запроса
	 * @param string $url
	 * @param string $method GET|POST|PUT|DELETE
	 * @param array $data POST, PUT, DELETE данные
	 * @param bool|array $basic [login, pwd] для basic-авторизации
	 * @param array $cookies
	 * @param array $rHeaders допольнительные http-заголовки
	 * @param int $hops максимальное количество редиректор (переходов по "Location: ...")
	 * @return Request
	 * @throws Except
	 */
	public static function go($url, $method = 'GET', $data = array(), $basic = false, $cookies = array(), $rHeaders = array(), $hops = 10) {
		$cookiestr = self::cookiesToString($cookies);

		$ch = curl_init($url);
		if($cookiestr) curl_setopt($ch, CURLOPT_COOKIE, $cookiestr); //set cookies

		if(is_array($basic)) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			curl_setopt($ch, CURLOPT_USERPWD, $basic[0].":".$basic[1]);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return result
		curl_setopt($ch, CURLOPT_VERBOSE, true); //detailed info
		curl_setopt($ch, CURLOPT_COOKIESESSION, true); //restart cookie session
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER , false); //no ssl verify
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100); //timeout
		curl_setopt($ch, CURLOPT_HEADER, 1); //add headers to result

		if($method == 'PUT' OR $method == 'DELETE') {
			$fields = http_build_query($data);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			$rHeaders[] = 'Content-Length: '.strlen($fields);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
		} elseif($method == 'POST') {
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		}

		//Устанавливает все headers
		if(sizeof($rHeaders)) curl_setopt($ch, CURLOPT_HTTPHEADER, $rHeaders);

		if(false === $result = curl_exec($ch)) {
			throw new Except('Unable to load URL '.$url, 'network_error', Except::TYPE_NETWORK);
		}

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$rawHeaders = trim(substr($result, 0, $header_size));
		$headers = explode("\r\n", $rawHeaders);
		$body = trim(substr($result, $header_size));
		$cookies = array_merge($cookies, self::parseCookies($headers));

		//Check "Location:" for redirect
		$cLocation = self::checkLocation($url, $headers);
		if($hops>0 AND false !== $cLocation) return self::go($cLocation, $method, $data, $basic, $cookies, $rHeaders, $hops-1);

		//Collecting result
		$request = new self;
		$request->rawHeaders = $rawHeaders;
		$request->headers = $headers;
		$request->assocHeaders = self::parseHeaders($headers);
		$request->body = $body;
		$request->cookies = $cookies;
		list($request->httpCode, $request->httpStatus) = self::parseHttpCode($headers);

		return $request;
	}

	/**
	 * Парсит результат как JSON и возвращает в виде ассоциативного массива
	 * @return mixed
	 * @throws Except
	 */
	public function getJSON() {
		if(null === $json = json_decode($this->body, true)) throw new Except('Unable to parse JSON', 'json_parse', Except::TYPE_PARSE);
		return $json;
	}

	/**
	 * Парсит первый header на code и status
	 * @param array $headers
	 * @return array|bool
	 */
	public static function parseHttpCode($headers = array()) {
		if(!sizeof($headers)) return false;
		$items = explode(' ', $headers[0]);
		array_shift($items);
		$code = (int)array_shift($items);
		return array($code, implode(' ', $items));
	}

	/**
	 * Вытаскивает cookies из headers
	 * @param array $headers
	 * @return array
	 */
	private static function parseCookies($headers = array()){
		$cookies = array();
		foreach($headers as $line) {
			$line = trim($line);
			if(0 === strpos($line, 'Set-Cookie:')) {
				$line = explode(':', $line); $line = $line[1];
				$line = explode(';', $line); $line = $line[0];
				$line = explode('=', $line);
				$cookies[trim($line[0])] = trim($line[1]);
			}
		}
		return $cookies;
	}

	/**
	 * Находит в headers "Location: ..." и формирует url для переадресации
	 * @param string $url исходный URL
	 * @param array $headers
	 * @return bool|string
	 */
	private static function checkLocation($url, $headers = array()) {
		foreach($headers as $line) {
			$line = trim($line);
			if(0 === strpos($line, 'Location:')) {
				$line = explode(':', $line); unset($line[0]); $line = trim(implode(':', $line));
				$pUrl = parse_url($url);
				$pLine = parse_url($line);
				if(!isset($pLine['host'])) $line = $pUrl['host'].$line;
				if(!isset($pLine['scheme'])) $line = $pUrl['scheme'].'://'.$line;
				return $line;
			}
		}
		return false;
	}

	/**
	 * Парсит массив headers на ассоциативный массив
	 * @param array $headers
	 * @return array
	 */
	private static function parseHeaders($headers = array()) {
		$res = array();
		foreach($headers as $item) {
			$h = explode(':', $item);
			if(sizeof($h) < 2) continue;
			$_h = array_shift($h);
			$res[trim($_h)] = trim(implode(':', $h));
		}
		return $res;
	}

	/**
	 * Формирует строку cookies для передачи в headers
	 * @param array $cookies
	 * @return string
	 */
	private static function cookiesToString($cookies = array()) {
		$arr = array();
		foreach($cookies as $k => $v) $arr[] = $k.'='.$v;
		return implode(';', $arr);
	}

}