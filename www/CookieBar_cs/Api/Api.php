<?php

namespace CookieBar\Api;

/**
 * Ported from Bronze CookieBar_cs. Changes: no login/terminal and no settings write
 * (Bronze has a hardcoded password and a public write endpoint), file names go through
 * basename() — the original allowed "../" and read any file on the server.
 */
final class Api
{

	private function __construct()
	{
		$request = Request::create();
		$name = basename((string) $request->data);

		if($request->action === 'html') {
			$response = static::getFile(__DIR__ . '/assets/html/' . $name . '.html', false);
		} elseif($request->action === 'css') {
			$response = static::getFile(__DIR__ . '/assets/css/' . $name . '.css', false);
		} elseif($request->action === 'image') {
			$response = static::getFile(__DIR__ . '/assets/images/' . $name, true);
		} elseif ($request->action === 'settings') {
			$response = static::getSettings();
		}

		if(!isset($response)) {
			$response = Response::create(['message' => sprintf('Bad request. Unknown action "%s".', $request->action)], Response::BadRequest);
		}

		$response->send();
	}

	/**
	 * @return static
	 */
	public static function create()
	{
		return new static();
	}

	/**
	 * @param string $filepath
	 * @param bool $dataUri
	 * @return Response
	 */
	private static function getFile($filepath, $dataUri)
	{
		if(!is_file($filepath)) {
			return Response::create(['message' => 'File not found.'], Response::BadRequest);
		}

		$content = file_get_contents($filepath);
		if($dataUri) {
			$mime = ['svg' => 'image/svg+xml', 'png' => 'image/png'][pathinfo($filepath, PATHINFO_EXTENSION)] ?? 'image/jpg';
			$content = 'data:' . $mime . ';base64,' . base64_encode($content);
		}

		return Response::create(['content' => $content], Response::Ok);
	}

	/**
	 * @return Response
	 */
	private static function getSettings()
	{
		$file = __DIR__ . DIRECTORY_SEPARATOR . 'settings.json';
		$content = is_file($file) ? file_get_contents($file) : '';
		$content = json_decode($content) ?:[];

		return Response::create($content, Response::Ok);
	}

}
