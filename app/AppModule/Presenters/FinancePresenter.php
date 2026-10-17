<?php declare(strict_types=1);

namespace App\Presentation\App\Finance;

use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\Transaction\Transaction;


final class FinancePresenter extends BasePresenter
{
	private ?Transaction $tx = null;

	#[Nette\Application\Attributes\Persistent]
	public ?string $month = null;

	#[Nette\Application\Attributes\Persistent]
	public ?string $category = null;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$month = preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', (string) $this->month) ? $this->month : date('Y-m');
		[$year, $m] = explode('-', $month);
		$start = new \DateTimeImmutable("$year-$m-01 00:00:00");
		$end = $start->modify('last day of this month')->setTime(23, 59, 59);

		$conditions = ['user' => $user, 'date>=' => $start, 'date<=' => $end];
		if ($this->category !== null && $this->category !== '') {
			$conditions['category'] = $this->category;
		}
		$txs = $this->orm->transactions->findBy($conditions)->orderBy('date', 'DESC');

		$income = $expense = 0.0;
		foreach ($txs as $t) {
			if ($t->type === Transaction::TYPE_INCOME) {
				$income += $t->amount;
			} else {
				$expense += $t->amount;
			}
		}

		$this->template->transactions = $txs;
		$this->template->month = $month;
		$this->template->monthStart = $start;
		$this->template->prevMonth = $start->modify('-1 month')->format('Y-m');
		$this->template->nextMonth = $start->modify('+1 month')->format('Y-m');
		$this->template->categories = $this->orm->transactions->getCategories($user);
		$this->template->selectedCategory = $this->category;
		$this->template->income = $income;
		$this->template->expense = $expense;
		$this->template->balance = $income - $expense;
	}


	public function actionAdd(?string $type = null): void
	{
		$this['transactionForm']->setDefaults([
			'date' => date('Y-m-d'),
			'type' => $type === Transaction::TYPE_INCOME ? Transaction::TYPE_INCOME : Transaction::TYPE_EXPENSE,
		]);
	}


	public function renderAdd(): void
	{
		$this->template->categories = $this->orm->transactions->getCategories($this->getCurrentUser());
	}


	public function actionEdit(int $id): void
	{
		$this->tx = $this->loadOwnedTx($id);
		$this['transactionForm']->setDefaults([
			'type' => $this->tx->type,
			'amount' => $this->tx->amount,
			'description' => $this->tx->description,
			'category' => $this->tx->category,
			'car' => $this->tx->car?->id,
			'date' => $this->tx->date->format('Y-m-d'),
		]);
	}


	public function renderEdit(int $id): void
	{
		$this->template->tx = $this->tx;
		$this->template->categories = $this->orm->transactions->getCategories($this->getCurrentUser());
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$tx = $this->loadOwnedTx($id);
		$this->orm->transactions->removeAndFlush($tx);
		$this->flashMessage('Transakce smazána.', 'success');
		$this->redirect('default');
	}


	protected function createComponentTransactionForm(): Form
	{
		$user = $this->getCurrentUser();
		$carOptions = [];
		foreach ($user->cars as $car) {
			$carOptions[$car->id] = $car->name;
		}

		$form = new Form;
		$form->addSelect('type', 'Typ', [
			Transaction::TYPE_EXPENSE => 'Výdaj',
			Transaction::TYPE_INCOME => 'Příjem',
		])->setRequired();
		$form->addFloat('amount', 'Částka (Kč)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 9999999999)->setRequired()
			->addRule(Form::MIN, 'Částka musí být větší než 0.', 0.01);
		$form->addText('description', 'Popis')->setRequired();
		$form->addText('category', 'Kategorie')->setRequired()
			->setHtmlAttribute('list', 'categories')
			->setHtmlAttribute('autocomplete', 'off');
		$form->addSelect('car', 'Auto (volitelné)', $carOptions)->setPrompt('—');
		$form->addDate('date', 'Datum')->setFormat('Y-m-d')->setRequired();
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$tx = $this->tx ?? new Transaction;
			if ($this->tx === null) {
				$tx->user = $this->getCurrentUser();
			}
			$tx->type = $data->type;
			$tx->amount = $data->amount;
			$tx->description = $data->description;
			$tx->category = $data->category;
			$tx->car = $data->car !== null ? $this->orm->cars->getById((int) $data->car) : null;
			$tx->carName = $tx->car?->name;
			$tx->date = new \DateTimeImmutable($data->date);

			// Verify selected car belongs to user
			if ($tx->car !== null && $tx->car->user->id !== $this->getCurrentUser()->id) {
				$form->addError('Neplatné auto.');
				return;
			}

			$this->orm->transactions->persistAndFlush($tx);
			$this->flashMessage($this->tx ? 'Transakce uložena.' : 'Transakce přidána.', 'success');
			$this->redirect('default');
		};
		return $form;
	}


	private function loadOwnedTx(int $id): Transaction
	{
		$tx = $this->orm->transactions->getById($id);
		if ($tx === null) {
			throw new BadRequestException('Transakce nenalezena.');
		}
		if ($tx->user->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Cizí transakce.');
		}
		return $tx;
	}
}
