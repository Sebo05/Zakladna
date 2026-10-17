<?php declare(strict_types=1);

namespace App\Model\Page;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<Page>
 */
final class PagesRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [Page::class];
	}


	public function findBySlug(string $slug): ?Page
	{
		return $this->getBy(['slug' => $slug]);
	}
}
