<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;


/**
 * OAuth2 authorization code + refresh token, shared by ČS and KB (ported from Bronze OAuthBankApi).
 * The refresh token is kept in `secret`, tokens/keys encrypted. Callers persist the account afterwards.
 */
abstract class OAuthApi
{
	/** [production, sandbox] */
	protected const Authorize = ['', ''];
	protected const Token = ['', ''];
	protected const Scope = '';
	protected const AuthorizeParams = [];


	public function __construct(
		protected readonly SecretService $secrets,
	) {
	}


	/** @return list<array{id: string, label: string}> accounts the user granted access to */
	abstract public function accounts(BankAccount $account): array;


	/** @return list<BankMovement> */
	abstract public function movements(BankAccount $account, DateTimeImmutable $since): array;


	/** @return array<string, string> app key headers (ČS `WEB-API-key`, KB `apiKey`) — sent to the token endpoint too */
	abstract protected function apiKeyHeaders(BankAccount $account): array;


	public function authorizationUrl(BankAccount $account, string $redirectUri, string $state): string
	{
		return static::Authorize[(int) $account->sandbox] . '?' . http_build_query([
			'response_type' => 'code',
			'client_id' => (string) $account->clientId,
			'redirect_uri' => $redirectUri,
			'scope' => static::Scope,
			'state' => $state,
		] + static::AuthorizeParams);
	}


	public function exchangeCode(BankAccount $account, string $code, string $redirectUri): void
	{
		$this->storeTokens($account, $this->tokenRequest($account, ['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri]));
	}


	/** @return array<string, string> */
	protected function headers(BankAccount $account): array
	{
		return [
			'Authorization' => 'Bearer ' . $this->accessToken($account),
			'Accept' => 'application/json',
		] + $this->apiKeyHeaders($account);
	}


	private function accessToken(BankAccount $account): string
	{
		if ($account->accessToken && $account->tokenExpiresAt && $account->tokenExpiresAt->getTimestamp() > time() + 60) {
			return (string) $this->secrets->decrypt($account->accessToken);
		}
		$refresh = $this->secrets->decrypt($account->secret) ?? throw new BankException('Účet není připojený — klikni na Připojit.');
		$this->storeTokens($account, $this->tokenRequest($account, ['grant_type' => 'refresh_token', 'refresh_token' => $refresh]));
		return (string) $this->secrets->decrypt($account->accessToken);
	}


	private function tokenRequest(BankAccount $account, array $params): array
	{
		if (!$account->clientId || !$account->clientSecret) {
			throw new BankException('Chybí client_id / client_secret.');
		}
		return Http::json('POST', static::Token[(int) $account->sandbox], [
			'Accept' => 'application/json',
			'Content-Type' => 'application/x-www-form-urlencoded',
		] + $this->apiKeyHeaders($account), $params + ['client_id' => $account->clientId, 'client_secret' => (string) $this->secrets->decrypt($account->clientSecret)]);
	}


	private function storeTokens(BankAccount $account, array $data): void
	{
		$access = Http::get($data, 'access_token') ?? throw new BankException('Banka nevrátila access_token: ' . (Http::get($data, 'error_description', 'error') ?? '?'));
		$account->accessToken = $this->secrets->encrypt((string) $access);
		if ($refresh = Http::get($data, 'refresh_token')) {
			$account->secret = $this->secrets->encrypt((string) $refresh);
		}
		$account->tokenExpiresAt = new DateTimeImmutable('+' . max(60, (int) (Http::get($data, 'expires_in') ?? 3600)) . ' seconds');
	}
}
