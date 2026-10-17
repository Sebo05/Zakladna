<?php declare(strict_types=1);

namespace App\Presentation\Admin\Pages;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\Strings;
use App\Presentation\Admin\BasePresenter;
use App\Model\Page\Page;
use App\Presentation\Frontend\Legal\LegalPresenter;


final class PagesPresenter extends BasePresenter
{
	private ?Page $editedPage = null;


	public function renderDefault(): void
	{
		$legalSlugs = array_column(LegalPresenter::Docs, 'slug');
		$this->template->pages = $this->orm->pages->findBy(['slug!=' => $legalSlugs])->orderBy('createdAt', 'DESC');
		$this->template->legal = array_map(fn($d) => $d + ['page' => $this->orm->pages->findBySlug($d['slug'])], LegalPresenter::Docs);
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
		$this->template->isLegal = $this->isLegal($this->editedPage);
	}


	/** Open a legal document for editing; first time it is created from the built-in text. */
	public function actionLegal(string $doc): void
	{
		$meta = LegalPresenter::Docs[$doc] ?? throw new BadRequestException;
		$page = $this->orm->pages->findBySlug($meta['slug']);
		if ($page === null) {
			$page = new Page;
			$page->title = $meta['title'];
			$page->slug = $meta['slug'];
			// placeholders stay in the stored text, LegalPresenter fills them from config
			$page->content = str_replace('&#123;', '{', $this->createTemplate()->renderToString(
				dirname((new \ReflectionClass(LegalPresenter::class))->getFileName()) . "/_$doc.latte",
				['operator' => ['name' => '{provozovatel}', 'email' => '{email}', 'city' => '{mesto}']],
			)); // Latte escapes { as &#123;
			$this->orm->pages->persistAndFlush($page);
		}
		$this->redirect('edit', $page->id);
	}


	private function isLegal(?Page $page): bool
	{
		return $page !== null && in_array($page->slug, array_column(LegalPresenter::Docs, 'slug'), true);
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
		// legal docs are found by slug — keep it
		$page->slug = $this->isLegal($this->editedPage) ? $page->slug : Strings::webalize($data->slug);
		$page->content = $data->content;
		$page->visible = $data->visible;

		$this->orm->pages->persistAndFlush($page);
		$this->flashMessage($this->editedPage ? 'Stránka uložena.' : 'Stránka přidána.', 'success');
		$this->redirect('default');
	}
}
