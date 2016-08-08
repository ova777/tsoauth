<?php
namespace ova777\TSOAuth;

class Except extends \Exception {
	//Типы исключений
	const TYPE_NETWORK = 'NETWORK';
	const TYPE_OAUTH = 'OAUTH';
	const TYPE_PARSE = 'PARSE';
	const TYPE_API = 'API';
	const TYPE_UNDEFINED = 'UNDEFINED';

	public $type;
	public $message;
	public $code;

	/**
	 * Except constructor.
	 * @param string $message
	 * @param int|string $code
	 * @param string $type
	 */
	public function __construct($message, $code, $type = self::TYPE_UNDEFINED) {
		if(!defined('self::TYPE_'.$type)) $type = self::TYPE_UNDEFINED;

		$this->type = $type;
		$this->message = $message;
		$this->code = $code;
	}

	public function asString() {
		return $this->type.': '.$this->code.' ('.$this->message.')';
	}

}