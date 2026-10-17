<?php declare(strict_types=1);

namespace App\FrontendModule;

use Nette;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use App\Model\Orm;


/**
 * Authenticator for end users (app module) — queries the `users` table.
 */
final class Authenticator implements Nette\Security\Authenticator
{
	public function __construct(
		private Orm $orm,
		private Nette\Security\Passwords $passwords,
	) {
	}


	public function authenticate(string $user, string $password): IIdentity
	{
		$row = $this->orm->users->findByEmail($user);
		if ($row === null) {
			throw new AuthenticationException('Neznámý uživatel.');
		}

		if (!$this->passwords->verify($password, $row->password)) {
			throw new AuthenticationException('Neplatné heslo.');
		}

		if ($this->passwords->needsRehash($row->password)) {
			$row->password = $this->passwords->hash($password);
			$this->orm->persistAndFlush($row);
		}

		return new SimpleIdentity(
			$row->id,
			['user'],
			['name' => $row->name, 'email' => $row->email],
		);
	}
}
