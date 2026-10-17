<?php

namespace CookieBar\Api;

final class Response
{

	const Ok  = 200;

  const BadRequest = 400;

	const InternalError = 500;

	/** @var array<mixed> */
	public $data;

	/** @var ?int */
	public $code;

	/**
	 * @param array<mixed> $data
	 * @param ?int $code
	 */
	private function __construct($data, $code)
	{
		$this->data = $data;
		$this->code = $code;
	}

	/**
	 * @param array<mixed> $data
	 * @param ?int $code
	 * @return static
	 */
  public static function create($data = [], $code = null)
  {
		return new static($data, $code);
  }

	/**
	 * @return void
	 */
	public function send()
	{
		header('HTTP/1.0 ' . (isset($this->code) ? $this->code : static::Ok));
		header('Content-Type: application/json; charset=utf-8');
		exit(json_encode($this->data));
	}

}