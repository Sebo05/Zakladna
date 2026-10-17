<?php declare(strict_types=1);

namespace App\Model\Bank;

use Nette\Utils\Json;
use Nette\Utils\JsonException;


/** Minimal cURL client for the bank APIs (no Guzzle in this project). */
final class Http
{
	/**
	 * @param array<string, string> $headers
	 * @param array<string, string> $form  sent as x-www-form-urlencoded when not empty
	 * @param array{string, ?string}|null $cert  client certificate (mTLS): [absolute path, password]
	 * @return array<mixed>
	 */
	public static function json(string $method, string $url, array $headers = [], array $form = [], ?array $cert = null): array
	{
		$body = self::request($method, $url, $headers, $form ? http_build_query($form) : null, $cert);
		try {
			$data = Json::decode($body, forceArrays: true);
		} catch (JsonException $e) {
			throw new BankException('Banka nevrátila platný JSON.', 0, $e);
		}
		return is_array($data) ? $data : [];
	}


	/**
	 * Raw response body; HTTP 4xx/5xx and network errors throw BankException.
	 * @param array<string, string> $headers
	 * @param array{string, ?string}|null $cert  .p12/.pfx is sent as PKCS#12, anything else as PEM
	 */
	public static function request(string $method, string $url, array $headers = [], ?string $body = null, ?array $cert = null): string
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTPHEADER => array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers),
		]);
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		if ($cert) {
			[$path, $password] = $cert;
			curl_setopt($ch, CURLOPT_SSLCERT, $path);
			if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['p12', 'pfx'], true)) {
				curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'P12');
			}
			if ($password !== null && $password !== '') {
				curl_setopt($ch, CURLOPT_KEYPASSWD, $password);
			}
		}
		$response = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		if ($response === false) {
			throw new BankException('Banka neodpověděla: ' . $error);
		}
		if ($status === 409 && str_contains($url, 'fio.cz')) {
			throw new BankException('Fio povoluje jeden dotaz za 30 sekund — zkus to za chvíli.');
		}
		if ($status >= 400) {
			throw new BankException(sprintf('Banka vrátila HTTP %d: %s', $status, mb_substr(trim(strip_tags((string) $response)), 0, 200)));
		}
		return (string) $response;
	}


	/** Nested value by dot path; first non-empty of several paths. */
	public static function get(array $data, string ...$paths): mixed
	{
		foreach ($paths as $path) {
			$value = $data;
			foreach (explode('.', $path) as $key) {
				$value = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
			}
			if ($value !== null && $value !== '') {
				return $value;
			}
		}
		return null;
	}
}
