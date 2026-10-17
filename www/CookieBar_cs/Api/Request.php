<?php

namespace CookieBar\Api;

final class Request
{

  /** @var ?string */
  public $action;

  /** @var ?string */
  public $data;

  /**
   * @param ?string $action
   * @param ?string $data
   */
  private function __construct($action, $data)
  {
    $this->action = $action;
    $this->data = $data;
  }

  /**
   * @return static
   */
  public static function create()
  {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    return new static(
      isset($data['action']) ? $data['action'] : null,
      isset($data['data']) ? $data['data'] : null
    );
  }

}