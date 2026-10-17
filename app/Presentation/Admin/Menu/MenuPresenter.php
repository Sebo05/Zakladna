<?php declare(strict_types=1);

namespace App\Presentation\Admin\Menu;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use App\Presentation\Admin\BasePresenter;
use App\Model\MenuItem\MenuItem;


final class MenuPresenter extends BasePresenter
{
	private ?MenuItem $editedItem = null;


	public function renderDefault(): void
	{
		$this->template->items = $this->orm->menuItems->findAll()->orderBy('position');
	}


	public function actionEdit(int $id): void
	{
		$item = $this->orm->menuItems->getById($id);
		if ($item === null) {
			throw new BadRequestException('Položka nenalezena.');
		}
		$this->editedItem = $item;
		$this['menuForm']->setDefaults([
			'label' => $item->label,
			'url' => $item->url,
			'position' => $item->position,
			'visible' => $item->visible,
		]);
	}


	public function renderEdit(int $id): void
	{
		$this->template->item = $this->editedItem;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$item = $this->orm->menuItems->getById($id);
		if ($item !== null) {
			$this->orm->menuItems->removeAndFlush($item);
			$this->flashMessage('Položka smazána.', 'success');
		}
		$this->redirect('default');
	}


	protected function createComponentMenuForm(): Form
	{
		$form = new Form;
		$form->addText('label', 'Popisek:')->setRequired();
		$form->addText('url', 'URL:')->setRequired();
		$form->addInteger('position', 'Pořadí:')->setDefaultValue(0);
		$form->addCheckbox('visible', 'Viditelná')->setDefaultValue(true);
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$item = $this->editedItem ?? new MenuItem;
			$item->label = $data->label;
			$item->url = $data->url;
			$item->position = $data->position;
			$item->visible = $data->visible;
			$this->orm->menuItems->persistAndFlush($item);
			$this->flashMessage($this->editedItem ? 'Položka uložena.' : 'Položka přidána.', 'success');
			$this->redirect('default');
		};
		return $form;
	}
}
