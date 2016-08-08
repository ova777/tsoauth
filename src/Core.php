<?php
namespace ova777\TSOAuth;

class Core {

	/**
	 * Конфигурация класса
	 * @var array
	 */
	private $config = array(
		'auto_refresh_token' => true,
		'id' => '',
		'secret' => '',
		'host' => 'https://tronsalt.ru',
		'access_token' => '',
		'refresh_token' => '',
		'scope' => 'user.data'
	);

	/**
	 * URL для запросов в OAuth
	 * @var array
	 */
	private static $urls = array(
		'authorization_code' => '{host}/oauth?response_type=code&client_id={id}&scope={scope}',
		'access_token' => '{host}/oauth/token',
		'refresh_token' => '{host}/oauth/token',
		'user_data' => '{host}/oauth/resource/user/data?access_token={access_token}',
		'api' => '{host}/oauth/resource/',
	);

	/**
	 * Получение токенов из $config
	 * @param string $name
	 * @return bool|string
	 */
	public function __get($name) {
		switch ($name) {
			case 'access_token':
				return $this->config['access_token'];
			case 'refresh_token':
				return $this->config['refresh_token'];
			default:
				return false;
		}
	}

	/**
	 * Конструктор класса
	 * @param array $config
	 * @return Core
	 */
	public static function create($config) {
		$core = new self;
		$core->config = array_merge($core->config, $config);
		return $core;
	}

	/**
	 * GET запрос в АПИ
	 * @param string $url
	 * @return mixed
	 * @throws Except
	 */
	public function apiGet($url) {
		$pthis = $this;
		$url = $this->makeApiUrl($url);
		return $this->autoRefreshToken(function() use ($pthis, $url){
			$req = Request::go($url, 'GET');
			$pthis::callStatic('checkApiError', array($req));
			return $pthis::callStatic('parseJSON', array($req->getJSON()));
		});
	}

	/**
	 * POST запрос в АПИ
	 * @param string $url
	 * @param array $data
	 * @return mixed
	 * @throws Except
	 */
	public function apiPost($url, $data = array()) {
		if(!isset($data['access_token'])) $data['access_token'] = $this->config['access_token'];
		$pthis = $this;
		$url = $this->makeApiUrl($url, false);
		return $this->autoRefreshToken(function() use ($pthis, $url, $data){
			$req = Request::go($url, 'POST', $data);
			$pthis::callStatic('checkApiError', array($req));
			return $pthis::callStatic('parseJSON', array($req->getJSON()));
		});
	}

	/**
	 * PUT запрос в АПИ
	 * @param string $url
	 * @param array $data
	 * @return mixed
	 * @throws Except
	 */
	public function apiPut($url, $data = array()) {
		$pthis = $this;
		$url = $this->makeApiUrl($url);
		return $this->autoRefreshToken(function() use ($pthis, $url, $data){
			$req = Request::go($url, 'PUT', $data);
			$pthis::callStatic('checkApiError', array($req));
			return $pthis::callStatic('parseJSON', array($req->getJSON()));
		});
	}

	/**
	 * DELETE запрос в АПИ
	 * @param string $url
	 * @param array $data
	 * @return mixed
	 * @throws Except
	 */
	public function apiDelete($url, $data = array()) {
		$pthis = $this;
		$url = $this->makeApiUrl($url);
		return $this->autoRefreshToken(function() use ($pthis, $url, $data){
			$req = Request::go($url, 'DELETE', $data);
			$pthis::callStatic('checkApiError', array($req));
			return $pthis::callStatic('parseJSON', array($req->getJSON()));
		});
	}

	/**
	 * Подготовить (рассчитать) транзакцию
	 * @param bool|string $sum
	 * @param bool|string $total
	 * @return mixed
	 * @throws Except
	 */
	public function makeTransaction($sum = false, $total = false) {
		$data = array();
		if(false !== $sum) $data['sum'] = $sum;
		if(false !== $total) $data['total'] = $total;
		return $this->apiPost('partner/transaction', $data);
	}

	/**
	 * Подтвердить/отменить подготовленную транзакцию
	 * @param string $action [actions][commit/rollback] из результатов подготовленной транзакции
	 * @return mixed
	 * @throws Except
	 */
	public function endTransaction($action) {
		return $this->apiGet($action);
	}

	/**
	 * Возвращает данные пользователя
	 * @return mixed
	 * @throws Except
	 */
	public function getUserData() {
		return $this->apiGet('user/data');
	}

	/**
	 * Получить access_token по refresh_token
	 * @return $this
	 * @throws Except
	 */
	public function getAccessTokenByRefreshToken() {
		$req = Request::go(
			self::getUrl('refresh_token'),
			'POST',
			array('grant_type' => 'refresh_token', 'refresh_token' => $this->config['refresh_token']),
			array($this->config['id'], $this->config['secret'])
		);

		$json = self::parseJSON($req->getJSON());
		$this->config['access_token'] = $json['access_token'];
		$this->config['refresh_token'] = $json['refresh_token'];

		return $this;
	}

	/**
	 * Получить access_token по временному авторизационному коду
	 * @param string $code
	 * @return $this
	 * @throws Except
	 */
	public function getAccessTokenByCode($code) {
		$req = Request::go(
			self::getUrl('access_token'),
			'POST',
			array('grant_type' => 'authorization_code', 'code' => $code),
			array($this->config['id'], $this->config['secret'])
		);

		$json = self::parseJSON($req->getJSON());
		$this->config['access_token'] = $json['access_token'];
		$this->config['refresh_token'] = $json['refresh_token'];

		return $this;
	}

	/**
	 * Редирект на oauth-авторизацию приложения у пользователя
	 */
	public function goAuthorizationCode() {
		header('Location: '.$this->getUrl('authorization_code'));
	}

	/**
	 * Функция для вызова private не-static методов класса
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public function callMethod($method, $args = array()) {
		if(!is_array($args)) $args = array($args);
		return call_user_func_array(array($this, $method), $args);
	}

	/**
	 * Функция двы вызова private static методов класса
	 * @param string $method
	 * @param array $args
	 * @return mixed
	 */
	public static function callStatic($method, $args = array()) {
		if(!is_array($args)) $args = array($args);
		return call_user_func_array(array(__CLASS__, $method), $args);
	}

	/**
	 * Выполняет callback, в случае исключения "expired_token" (если auto_refresh_token) - обновляет access_token и callback снова
	 * @param $callback
	 * @return mixed
	 * @throws Except
	 */
	private function autoRefreshToken($callback) {
		try {
			return $callback();
		} catch (Except $e) {
			if($this->config['auto_refresh_token'] && $e->code == 'expired_token') {
				$this->getAccessTokenByRefreshToken();
				return $callback();
			}
			throw $e;
		}
	}

	/**
	 * Проверяет наличие ошибки при запросе в АПИ
	 * @param Request $req
	 * @throws Except
	 */
	private static function checkApiError(Request $req) {
		if($req->httpCode >= 400) throw new Except($req->httpStatus, $req->httpCode, Except::TYPE_API);
	}

	/**
	 * Проверяет полученный JSON на наличие OAuth-исключений
	 * @param mixed $json
	 * @return mixed
	 * @throws Except
	 */
	private static function parseJSON($json) {
		if(!$json) throw new Except('Empty result', 'json_empty', Except::TYPE_PARSE);
		if(isset($json['error']) && isset($json['error_description'])) throw new Except($json['error_description'], $json['error'], Except::TYPE_OAUTH);
		return $json;
	}

	/**
	 * Формирует URL для GET/POST/PUT/DELETE запросов в АПИ
	 * @param string $url путь АПИ
	 * @param bool $addAccessToken добавить в GET-параметры access_token
	 * @return string
	 */
	private function makeApiUrl($url, $addAccessToken = true) {
		$parsed = parse_url($url);
		$path = trim($parsed['path'], '/');
		$query = array();
		if(isset($parsed['query'])) parse_str($parsed['query'], $query);
		if(!isset($query['access_token']) && $addAccessToken) $query['access_token'] = $this->config['access_token'];

		return $this->getUrl('api').$path.($query ? '?'.http_build_query($query) : '');
	}

	/**
	 * Формирует URL необходимого типа
	 * @param string $type
	 * @return bool|string
	 */
	private function getUrl($type) {
		if(!isset(self::$urls[$type])) return false;
		$replace = array();
		foreach($this->config as $key => $val) {
			if(is_array($val)) $val = System::encodeURIComponent(implode(' ', $val));
			$replace['{'.$key.'}'] = $val;
		}
		return strtr(self::$urls[$type], $replace);
	}

}