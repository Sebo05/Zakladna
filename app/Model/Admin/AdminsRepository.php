<?php declare(strict_types=1);

namespace App\Model\Admin;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Admin>
 */
final class AdminsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Admin::class];
	}


	public function findByEmail(string $email): ?Admin
	{
		return $this->getBy(['email' => $email]);
	}
}
