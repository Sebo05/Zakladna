<?php declare(strict_types=1);

namespace App\Presentation\App\Budget;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\Budget\Budget;


final class BudgetPresenter extends BasePresenter
{
	private ?Budget $budget = null;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$now = new \DateTimeImmutable;
		$spent = $this->orm->transactions->getMonthlyExpensesByCategory($user, $now);

		$rows = [];
		$totalLimit = $totalSpent = 0.0;
		foreach ($this->orm->budgets->findBy(['user' => $user])->orderBy('category') as $b) {
			$s = $spent[$b->category] ?? 0.0;
			$rows[] = ['budget' => $b, 'spent' => $s, 'pct' => $b->monthlyLimit > 0 ? $s / $b->monthlyLimit * 100 : 0];
			$totalLimit += $b->monthlyLimit;
			$totalSpent += $s;
			unset($spent[$b->category]);
		}
		arsort($spent);

		$this->template->rows = $rows;
		$this->template->unbudgeted = $spent; // categories with spending but no limit
		$this->template->totalLimit = $totalLimit;
		$this->template->totalSpent = $totalSpent;
		$this->template->now = $now;
		// share of the month elapsed — for the "on track" marker
		$this->template->monthProgress = (int) $now->format('j') / (int) $now->format('t') * 100;
	}


	public function actionAdd(?string $category = null): void
	{
		if ($category !== null) {
			$this['budgetForm']->setDefaults(['category' => $category]);
		}
	}


	public function actionEdit(int $id): void
	{
		$this->budget = $this->loadOwnedBudget($id);
		$this['budgetForm']->setDefaults([
			'category' => $this->budget->category,
			'monthlyLimit' => $this->budget->monthlyLimit,
		]);
	}


	#[Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$this->orm->budgets->removeAndFlush($this->loadOwnedBudget($id));
		$this->flashMessage('Rozpočet smazán.', 'success');
		$this->redirect('default');
	}


	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->budget = $this->budget;
		$this->template->categories = $this->orm->transactions->getCategories($this->getCurrentUser());
	}


	protected function createComponentBudgetForm(): Form
	{
		$form = new Form;
		$form->addText('category', 'Kategorie')
			->setRequired('Zadej kategorii.')
			->setHtmlAttribute('list', 'categories')
			->setHtmlAttribute('autocomplete', 'off');
		$form->addFloat('monthlyLimit', 'Měsíční limit (Kč)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 9999999999)
			->setRequired('Zadej limit.')
			->addRule($form::Min, 'Limit musí být víc než 0.', 1);
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$user = $this->getCurrentUser();
			$existing = $this->orm->budgets->getBy(['user' => $user, 'category' => $data->category]);
			if ($existing !== null && $existing !== $this->budget) {
				$form['category']->addError('Pro tuto kategorii už rozpočet máš.');
				return;
			}
			$budget = $this->budget ?? new Budget;
			$budget->user = $user;
			$budget->category = $data->category;
			$budget->monthlyLimit = $data->monthlyLimit;
			$this->orm->budgets->persistAndFlush($budget);
			$this->flashMessage('Rozpočet uložen.', 'success');
			$this->redirect('default');
		};
		return $form;
	}


	private function loadOwnedBudget(int $id): Budget
	{
		$b = $this->orm->budgets->getById($id) ?? throw new BadRequestException('Rozpočet nenalezen.');
		if ($b->user->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Cizí rozpočet.');
		}
		return $b;
	}
}
