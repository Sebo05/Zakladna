<?php declare(strict_types=1);

namespace App\Presentation\Frontend;

use Nette\Application\UI\Presenter;
use App\Model\Orm;


abstract class BasePresenter extends Presenter
{
	/** @inject */
	public Orm $orm;

	/** @var array{name: string, email: string, city: string} set from config (operator) */
	public array $operator = [];

	public string $legalDate = '';


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->operator = $this->operator;
		$this->template->legalDate = new \DateTimeImmutable($this->legalDate);
		$this->template->menuItems = $this->orm->menuItems
			->findBy(['visible' => true])
			->orderBy('position');
	}
}
