<?php declare(strict_types=1);

namespace App\Presentation\Frontend\Page;

use Nette\Application\BadRequestException;
use App\Presentation\Frontend\BasePresenter;


final class PagePresenter extends BasePresenter
{
	public function actionDetail(string $slug): void
	{
		$page = $this->orm->pages->findBySlug($slug);
		if ($page === null || !$page->visible) {
			throw new BadRequestException('Stránka nenalezena.', 404);
		}
		$this->template->page = $page;
	}
}
