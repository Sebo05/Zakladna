<?php declare(strict_types=1);

namespace App\Presentation\Admin\Pages;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;
use App\Presentation\Admin\BasePresenter;
use App\Model\Page\Page;


final class PagesPresenter extends BasePresenter
{
	private ?Page $editedPage = null;


	public function renderDefault(): void
	{
		$this->template->pages = $this->orm->pages->findAll()->orderBy('createdAt', 'DESC');
	}


	public function actionEdit(int $id): void
	{
		$page = $this->orm->pages->getById($id);
		if ($page === null) {
			throw new BadRequestException('Stránka nenalezena.');
		}
		$this->editedPage = $page;
		$this['pageForm']->setDefaults([
			'title' => $page->title,
			'slug' => $page->slug,
			'content' => $page->content,
			'visible' => $page->visible,
		]);
	}


	public function renderEdit(int $id): void
	{
		$this->template->page = $this->editedPage;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$page = $this->orm->pages->getById($id);
		if ($page !== null) {
			$this->orm->pages->removeAndFlush($page);
			$this->flashMessage('Stránka smazána.', 'success');
		}
		$this->redirect('default');
	}


	protected function createComponentPageForm(): Form
	{
		$form = new Form;
		$form->addText('title', 'Název:')
			->setRequired('Zadejte název.');
		$form->addText('slug', 'Slug:')
			->setRequired('Zadejte slug.');
		$form->addTextArea('content', 'Obsah:')
			->setHtmlAttribute('rows', 12);
		$form->addCheckbox('visible', 'Viditelná');
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = $this->pageFormSucceeded(...);
		return $form;
	}


	private function pageFormSucceeded(Form $form, \stdClass $data): void
	{
		$page = $this->editedPage ?? new Page;
		$page->title = $data->title;
		$page->slug = Strings::webalize($data->slug);
		$page->content = $data->content;
		$page->visible = $data->visible;

		$this->orm->pages->persistAndFlush($page);
		$this->flashMessage($this->editedPage ? 'Stránka uložena.' : 'Stránka přidána.', 'success');
		$this->redirect('default');
	}
}
