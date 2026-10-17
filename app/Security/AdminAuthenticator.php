<?php declare(strict_types=1);

namespace App\Security;

use Nette;
use Nette\Security\AuthenticationException;
use Nette\Security\IIdentity;
use Nette\Security\SimpleIdentity;
use App\Model\Orm;


/**
 * Authenticator for the CMS admin area — queries the `admins` table.
 */
final class AdminAuthenticator implements Nette\Security\Authenticator
{
	public function __construct(
		private Orm $orm,
		private Nette\Security\Passwords $passwords,
	) {
	}


	public function authenticate(string $user, string $password): IIdentity
	{
		$row = $this->orm->admins->findByEmail($user);
		if ($row === null) {
			throw new AuthenticationException('Neznámý administrátor.');
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
			['admin'],
			['name' => $row->name, 'email' => $row->email],
		);
	}
}
