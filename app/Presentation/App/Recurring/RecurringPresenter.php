<?php declare(strict_types=1);

namespace App\Presentation\App\Recurring;

use DateTimeImmutable;
use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\Recurring\RecurringTransaction;
use App\Model\Transaction\Transaction;


/** Recurring payments: nothing is booked automatically — due items wait for Zapsat / Přeskočit. */
final class RecurringPresenter extends BasePresenter
{
	public const Periods = [RecurringTransaction::PERIOD_MONTH => 'Měsíčně', RecurringTransaction::PERIOD_YEAR => 'Ročně'];

	private ?RecurringTransaction $item = null;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$all = $this->orm->recurringTransactions->findBy(['user' => $user])->orderBy('nextDate')->fetchAll();
		$this->template->due = $this->orm->recurringTransactions->findDue($user);
		$this->template->items = $all;
		// monthly equivalent of active expenses / incomes
		$monthly = ['expense' => 0.0, 'income' => 0.0];
		foreach ($all as $r) {
			if (!$r->finished) {
				$monthly[$r->type] += $r->period === RecurringTransaction::PERIOD_YEAR ? $r->amount / 12 : $r->amount;
			}
		}
		$this->template->monthly = $monthly;
	}


	public function actionEdit(int $id): void
	{
		$this->item = $this->loadOwned($id);
		$this['form']->setDefaults([
			'type' => $this->item->type,
			'description' => $this->item->description,
			'amount' => $this->item->amount,
			'category' => $this->item->category,
			'period' => $this->item->period,
			'nextDate' => $this->item->nextDate->format('Y-m-d'),
			'endDate' => $this->item->endDate?->format('Y-m-d'),
		]);
	}


	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->item = $this->item;
		$this->template->categories = $this->orm->transactions->getCategories($this->getCurrentUser());
	}


	/** Book the due occurrence as a transaction and move to the next one. */
	#[Requires(sameOrigin: true)]
	public function handleConfirm(int $id): void
	{
		$r = $this->loadOwned($id);
		$t = new Transaction;
		$t->user = $r->user;
		$t->type = $r->type;
		$t->amount = $r->amount;
		$t->description = $r->description;
		$t->category = $r->category;
		$t->date = $r->nextDate;
		$r->advance();
		$this->orm->persist($t);
		$this->orm->persistAndFlush($r);
		$this->flashMessage("Zapsáno: {$r->description}.", 'success');
		$this->redirect('this');
	}


	#[Requires(sameOrigin: true)]
	public function handleSkip(int $id): void
	{
		$r = $this->loadOwned($id);
		$r->advance();
		$this->orm->persistAndFlush($r);
		$this->flashMessage("Přeskočeno: {$r->description}.", 'info');
		$this->redirect('this');
	}


	#[Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$this->orm->recurringTransactions->removeAndFlush($this->loadOwned($id));
		$this->flashMessage('Opakovaná platba smazána.', 'success');
		$this->redirect('default');
	}


	protected function createComponentForm(): Form
	{
		$form = new Form;
		$form->addRadioList('type', 'Typ', [RecurringTransaction::TYPE_EXPENSE => 'Výdaj', RecurringTransaction::TYPE_INCOME => 'Příjem'])
			->setDefaultValue(RecurringTransaction::TYPE_EXPENSE);
		$form->addText('description', 'Název')
			->setRequired('Zadej název, např. Nájem.')
			->setMaxLength(255)
			->setHtmlAttribute('placeholder', 'Nájem, Netflix, leasing…');
		$form->addFloat('amount', 'Částka (Kč)')
			->setRequired('Zadej částku.')
			->addRule($form::Range, 'Zadej částku mezi %d a %d.', [1, 9999999999]);
		$form->addText('category', 'Kategorie')
			->setRequired('Zadej kategorii.')
			->setMaxLength(100)
			->setHtmlAttribute('list', 'categories')
			->setHtmlAttribute('autocomplete', 'off');
		$form->addSelect('period', 'Opakování', self::Periods)->setDefaultValue(RecurringTransaction::PERIOD_MONTH);
		$form->addDate('nextDate', 'Příští platba')
			->setFormat('Y-m-d')
			->setRequired('Zadej datum.')
			->setDefaultValue((new DateTimeImmutable('today'))->format('Y-m-d'));
		$form->addDate('endDate', 'Konec (volitelné)')
			->setFormat('Y-m-d');
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$r = $this->item ?? new RecurringTransaction;
			$r->user = $this->getCurrentUser();
			$r->type = $data->type;
			$r->description = $data->description;
			$r->amount = $data->amount;
			$r->category = $data->category;
			$r->period = $data->period;
			$r->nextDate = new DateTimeImmutable($data->nextDate);
			$r->endDate = $data->endDate ? new DateTimeImmutable($data->endDate) : null;
			$this->orm->recurringTransactions->persistAndFlush($r);
			$this->flashMessage('Opakovaná platba uložena.', 'success');
			$this->redirect('default');
		};
		return $form;
	}


	private function loadOwned(int $id): RecurringTransaction
	{
		$r = $this->orm->recurringTransactions->getById($id) ?? throw new BadRequestException('Platba nenalezena.');
		if ($r->user->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Cizí platba.');
		}
		return $r;
	}
}
