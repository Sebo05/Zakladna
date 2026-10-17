<?php declare(strict_types=1);

namespace App\Model\User;

use Nette\Security\Passwords;
use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<User>
 */
final class UsersRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [User::class];
	}


	public function findByEmail(string $email): ?User
	{
		return $this->getBy(['email' => $email]);
	}


	public function register(string $name, string $email, string $plainPassword, Passwords $passwords): User
	{
		$user = new User;
		$user->name = $name;
		$user->email = $email;
		$user->password = $passwords->hash($plainPassword);
		$this->persistAndFlush($user);
		return $user;
	}
}
