<?php declare(strict_types=1);

namespace App\Presentation\Frontend;

use Nette\Application\UI\Presenter;
use App\Model\Orm;


abstract class BasePresenter extends Presenter
{
	/** @inject */
	public Orm $orm;


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->menuItems = $this->orm->menuItems
			->findBy(['visible' => true])
			->orderBy('position');
	}
}
