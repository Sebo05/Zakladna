<?php declare(strict_types=1);

namespace App\Presentation\Frontend\Home;

use App\Presentation\Frontend\BasePresenter;


final class HomePresenter extends BasePresenter
{
	public function renderDefault(): void
	{
		$this->template->pages = $this->orm->pages->findBy(['visible' => true])->orderBy('createdAt', 'DESC')->limitBy(5);
	}
}
