<?php declare(strict_types=1);

namespace App\Security;

use App\Model\Bank\Http;


/**
 * "Sign in with Google" — OAuth 2.0 authorization code flow (OpenID Connect, scope openid email profile).
 * Client ID/secret from Google Cloud Console → APIs & Services → Credentials → OAuth client (Web application),
 * authorized redirect URI = <appUrl>/login/google. Empty clientId = the button is hidden.
 */
final class GoogleAuth
{
	private const Authorize = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const Token = 'https://oauth2.googleapis.com/token';
	private const UserInfo = 'https://openidconnect.googleapis.com/v1/userinfo';


	public function __construct(
		private readonly string $clientId,
		private readonly string $clientSecret,
	) {
	}


	public function isEnabled(): bool
	{
		return $this->clientId !== '' && $this->clientSecret !== '';
	}


	public function authorizeUrl(string $redirectUri, string $state): string
	{
		return self::Authorize . '?' . http_build_query([
			'client_id' => $this->clientId,
			'redirect_uri' => $redirectUri,
			'response_type' => 'code',
			'scope' => 'openid email profile',
			'state' => $state,
			'prompt' => 'select_account',
		]);
	}


	/**
	 * Exchanges the code and returns the verified Google profile. Throws Model\Bank\BankException on HTTP errors.
	 * @return array{sub: string, email: string, name: string}|null  null = e-mail not verified by Google
	 */
	public function profile(string $code, string $redirectUri): ?array
	{
		$token = Http::json('POST', self::Token, [], [
			'code' => $code,
			'client_id' => $this->clientId,
			'client_secret' => $this->clientSecret,
			'redirect_uri' => $redirectUri,
			'grant_type' => 'authorization_code',
		]);
		$info = Http::json('GET', self::UserInfo, ['Authorization' => 'Bearer ' . ($token['access_token'] ?? '')]);
		if (empty($info['sub']) || empty($info['email']) || ($info['email_verified'] ?? false) !== true) {
			return null;
		}
		return [
			'sub' => (string) $info['sub'],
			'email' => mb_strtolower((string) $info['email']),
			'name' => mb_substr((string) ($info['given_name'] ?? $info['name'] ?? strstr((string) $info['email'], '@', true)), 0, 100),
		];
	}
}
