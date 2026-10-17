<?php declare(strict_types=1);

namespace App\Model\Widget;

use Nextras\Dbal\Connection;


/** Tokens for the desktop widget (Bearer). Only SHA-256 of the token is stored. */
final class WidgetTokenService
{
	public function __construct(
		private readonly Connection $db,
	) {
	}


	public function issue(int $userId): string
	{
		$token = bin2hex(random_bytes(32));
		$this->db->query('INSERT INTO widget_tokens %values', ['user_id' => $userId, 'token_hash' => hash('sha256', $token)]);
		return $token;
	}


	public function userId(string $token): ?int
	{
		$hash = hash('sha256', $token);
		$id = $this->db->query('SELECT user_id FROM widget_tokens WHERE token_hash = %s', $hash)->fetchField();
		if ($id !== null) {
			$this->db->query('UPDATE widget_tokens SET last_used_at = NOW() WHERE token_hash = %s', $hash);
		}
		return $id === null ? null : (int) $id;
	}


	public function count(int $userId): int
	{
		return (int) $this->db->query('SELECT COUNT(*) FROM widget_tokens WHERE user_id = %i', $userId)->fetchField();
	}


	public function revokeAll(int $userId): void
	{
		$this->db->query('DELETE FROM widget_tokens WHERE user_id = %i', $userId);
	}
}
