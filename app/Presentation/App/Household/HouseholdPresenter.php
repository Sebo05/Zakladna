<?php declare(strict_types=1);

namespace App\Presentation\App\Household;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\Random;
use App\Presentation\App\BasePresenter;
use App\Model\Household\Household;
use App\Model\Household\HouseholdBudget;
use App\Model\Household\HouseholdExpense;
use App\Model\Household\HouseholdExpenseShare;
use App\Model\Household\HouseholdItem;
use App\Model\Household\HouseholdSettlement;
use App\Model\Household\Ledger;
use App\Model\User\User;


/**
 * Shared household: expenses split between members (who owes whom), tasks, shopping list, budgets.
 * Views: default (overview), expenses, lists, budget, settings. Without a household only "default" (create/join) works.
 */
final class HouseholdPresenter extends BasePresenter
{
	private ?Household $household = null;


	public function startup(): void
	{
		parent::startup();
		$this->household = $this->getCurrentUser()->household;
		if ($this->household === null && !in_array($this->getAction(), ['default', 'join'], true)) {
			$this->redirect('default');
		}
	}


	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->household = $this->household;
		$this->template->me = $this->getCurrentUser();
		if ($this->household) {
			$this->template->members = $this->household->members->toCollection()->orderBy('name')->fetchAll();
		}
	}


	// ---------- overview ----------

	public function renderDefault(): void
	{
		if ($this->household === null) {
			return;
		}
		$members = $this->household->members->toCollection()->fetchPairs('id');
		$balances = $this->household->getBalances();
		$this->template->balances = $balances;
		$this->template->payments = Ledger::simplify($balances);
		$this->template->memberMap = $members;
		$this->template->inviteUrl = $this->link('//join', ['code' => $this->household->inviteCode]);
		$this->template->recentExpenses = $this->orm->householdExpenses->findBy(['household' => $this->household])
			->orderBy('date', 'DESC')->orderBy('id', 'DESC')->limitBy(5);
		$this->template->openTasks = $this->orm->householdItems->findBy(['household' => $this->household, 'kind' => HouseholdItem::KIND_TASK, 'done' => false])->countStored();
		$this->template->openShopping = $this->orm->householdItems->findBy(['household' => $this->household, 'kind' => HouseholdItem::KIND_SHOPPING, 'done' => false])->countStored();
		$this->template->monthSpent = array_sum($this->getMonthlyByCategory());
	}


	public function actionJoin(?string $code = null): void
	{
		if ($code !== null) {
			$this->joinByCode($code);
		}
		$this->redirect('default');
	}


	/** Records a settlement payment suggested by the ledger. */
	public function handleSettle(int $from, int $to, float $amount): void
	{
		$me = $this->getCurrentUser();
		$fromUser = $this->loadMember($from);
		$toUser = $this->loadMember($to);
		if ($me->id !== $from && $me->id !== $to) {
			throw new ForbiddenRequestException('Vyrovnat může jen ten, kdo platí nebo dostává.');
		}
		$s = new HouseholdSettlement;
		$s->household = $this->household;
		$s->fromUser = $fromUser;
		$s->toUser = $toUser;
		$s->amount = round(abs($amount), 2);
		$s->date = new \DateTimeImmutable;
		$this->orm->householdSettlements->persistAndFlush($s);
		$this->flashMessage("Zaznamenáno: {$fromUser->name} → {$toUser->name} " . number_format($s->amount, 0, ',', ' ') . ' Kč', 'success');
		$this->redirect('this');
	}


	// ---------- expenses ----------

	public function renderExpenses(): void
	{
		$this->template->expenses = $this->orm->householdExpenses->findBy(['household' => $this->household])
			->orderBy('date', 'DESC')->orderBy('id', 'DESC')->limitBy(100);
		$this->template->settlements = $this->orm->householdSettlements->findBy(['household' => $this->household])
			->orderBy('date', 'DESC')->limitBy(20);
		$this->template->categories = array_values(array_unique(array_merge(
			['Nákup', 'Bydlení', 'Energie', 'Jídlo', 'Domácnost'],
			$this->orm->householdExpenses->findBy(['household' => $this->household])->fetchPairs(null, 'category'),
		)));
	}


	#[Requires(sameOrigin: true)]
	public function actionDeleteExpense(int $id): void
	{
		$e = $this->orm->householdExpenses->getById($id) ?? throw new BadRequestException;
		$this->assertInHousehold($e->household);
		if ($e->paidBy->id !== $this->getCurrentUser()->id && $this->household->owner->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Smazat může jen plátce nebo správce domácnosti.');
		}
		$this->orm->householdExpenses->removeAndFlush($e);
		$this->flashMessage('Výdaj smazán.', 'success');
		$this->redirect('expenses');
	}


	protected function createComponentExpenseForm(): Form
	{
		$members = $this->household?->members->toCollection()->orderBy('name')->fetchPairs('id', 'name') ?? [];
		$form = new Form;
		$form->addFloat('amount', 'Částka (Kč)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 9999999999)->setRequired('Zadej částku.')->addRule($form::Min, 'Částka musí být větší než 0.', 0.01);
		$form->addText('description', 'Za co')->setRequired('Napiš, za co to bylo.')->setHtmlAttribute('placeholder', 'Nákup v Albertu, nájem…');
		$form->addText('category', 'Kategorie')->setRequired()->setDefaultValue('Nákup')
			->setHtmlAttribute('list', 'hh-categories')->setHtmlAttribute('autocomplete', 'off');
		$form->addDate('date', 'Datum')->setFormat('Y-m-d')->setRequired()->setDefaultValue(date('Y-m-d'));
		$form->addSelect('paidBy', 'Zaplatil(a)', $members)->setDefaultValue($this->getCurrentUser()->id)->setRequired();
		$form->addCheckboxList('splitAmong', 'Rozdělit mezi', $members)
			->setDefaultValue(array_keys($members))
			->setRequired('Vyber aspoň jednoho člena.');
		$form->addSubmit('send', 'Přidat výdaj');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$e = new HouseholdExpense;
			$e->household = $this->household;
			$e->paidBy = $this->loadMember((int) $data->paidBy);
			$e->amount = $data->amount;
			$e->description = $data->description;
			$e->category = $data->category;
			$e->date = new \DateTimeImmutable($data->date);
			$ids = array_map('intval', array_values($data->splitAmong));
			foreach (Ledger::splitEqually($data->amount, $ids) as $uid => $amt) {
				$share = new HouseholdExpenseShare;
				$share->user = $this->loadMember($uid);
				$share->amount = $amt;
				$e->shares->add($share);
			}
			$this->orm->householdExpenses->persistAndFlush($e);
			$this->flashMessage('Výdaj přidán a rozpočítán.', 'success');
			$this->redirect('this');
		};
		return $form;
	}


	// ---------- tasks & shopping ----------

	public function renderLists(): void
	{
		$items = $this->orm->householdItems->findBy(['household' => $this->household])->orderBy('done')->orderBy('createdAt', 'DESC');
		$tasks = $shopping = [];
		foreach ($items as $i) {
			if ($i->kind === HouseholdItem::KIND_TASK) {
				$tasks[] = $i;
			} else {
				$shopping[] = $i;
			}
		}
		$this->template->tasks = $tasks;
		$this->template->shopping = $shopping;
	}


	public function handleToggle(int $id): void
	{
		$item = $this->loadItem($id);
		$item->done = !$item->done;
		$this->orm->householdItems->persistAndFlush($item);
		$this->redirect('this');
	}


	public function handleDeleteItem(int $id): void
	{
		$this->orm->householdItems->removeAndFlush($this->loadItem($id));
		$this->redirect('this');
	}


	public function handleClearDone(string $kind): void
	{
		foreach ($this->orm->householdItems->findBy(['household' => $this->household, 'kind' => $kind, 'done' => true]) as $i) {
			$this->orm->householdItems->remove($i);
		}
		$this->orm->flush();
		$this->redirect('this');
	}


	protected function createComponentTaskForm(): Form
	{
		$form = $this->createItemForm(HouseholdItem::KIND_TASK, 'Nový úkol…');
		$members = $this->household?->members->toCollection()->orderBy('name')->fetchPairs('id', 'name') ?? [];
		$form->addSelect('assignee', null, $members)->setPrompt('Kdokoliv');
		$form->addDate('dueDate')->setFormat('Y-m-d');
		return $form;
	}


	protected function createComponentShoppingForm(): Form
	{
		return $this->createItemForm(HouseholdItem::KIND_SHOPPING, 'Co koupit…');
	}


	private function createItemForm(string $kind, string $placeholder): Form
	{
		$form = new Form;
		$form->addText('title')->setRequired('Napiš text.')->setMaxLength(200)->setHtmlAttribute('placeholder', $placeholder);
		$form->addSubmit('send', 'Přidat');
		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($kind): void {
			$item = new HouseholdItem;
			$item->household = $this->household;
			$item->kind = $kind;
			$item->title = $data->title;
			$item->createdBy = $this->getCurrentUser();
			if ($kind === HouseholdItem::KIND_TASK) {
				$item->assignee = $data->assignee ? $this->loadMember((int) $data->assignee) : null;
				$item->dueDate = $data->dueDate ? new \DateTimeImmutable($data->dueDate) : null;
			}
			$this->orm->householdItems->persistAndFlush($item);
			$this->redirect('this');
		};
		return $form;
	}


	// ---------- budget ----------

	public function renderBudget(): void
	{
		$spent = $this->getMonthlyByCategory();
		$rows = [];
		foreach ($this->household->budgets->toCollection()->orderBy('category') as $b) {
			$s = $spent[$b->category] ?? 0.0;
			$rows[] = ['budget' => $b, 'spent' => $s, 'pct' => $s / $b->monthlyLimit * 100];
			unset($spent[$b->category]);
		}
		arsort($spent);
		$this->template->rows = $rows;
		$this->template->unbudgeted = $spent;
		$now = new \DateTimeImmutable;
		$this->template->now = $now;
		$this->template->monthProgress = (int) $now->format('j') / (int) $now->format('t') * 100;
	}


	#[Requires(sameOrigin: true)]
	public function actionDeleteBudget(int $id): void
	{
		$b = $this->orm->householdBudgets->getById($id) ?? throw new BadRequestException;
		$this->assertInHousehold($b->household);
		$this->orm->householdBudgets->removeAndFlush($b);
		$this->redirect('budget');
	}


	protected function createComponentBudgetForm(): Form
	{
		$form = new Form;
		$form->addText('category', 'Kategorie')->setRequired('Zadej kategorii.')->setHtmlAttribute('list', 'hh-categories');
		$form->addFloat('monthlyLimit', 'Měsíční limit (Kč)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 9999999999)->setRequired('Zadej limit.')->addRule($form::Min, 'Limit musí být větší než 0.', 1);
		$form->addSubmit('send', 'Přidat');
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$b = $this->orm->householdBudgets->getBy(['household' => $this->household, 'category' => $data->category]) ?? new HouseholdBudget;
			$b->household = $this->household;
			$b->category = $data->category;
			$b->monthlyLimit = $data->monthlyLimit;
			$this->orm->householdBudgets->persistAndFlush($b);
			$this->flashMessage('Rozpočet uložen.', 'success');
			$this->redirect('this');
		};
		return $form;
	}


	// ---------- settings / membership ----------

	public function handleRegenerateCode(): void
	{
		$this->assertOwner();
		$this->household->inviteCode = $this->generateCode();
		$this->orm->households->persistAndFlush($this->household);
		$this->flashMessage('Nový kód vytvořen. Starý přestal platit.', 'success');
		$this->redirect('this');
	}


	public function handleRemoveMember(int $id): void
	{
		$this->assertOwner();
		$member = $this->loadMember($id);
		if ($member->id === $this->getCurrentUser()->id) {
			throw new BadRequestException;
		}
		$member->household = null;
		$this->orm->users->persistAndFlush($member);
		$this->flashMessage("{$member->name} byl(a) odebrán(a).", 'success');
		$this->redirect('this');
	}


	#[Requires(sameOrigin: true)]
	public function actionLeave(): void
	{
		$me = $this->getCurrentUser();
		$hh = $this->household;
		$others = array_values(array_filter($hh->members->toCollection()->fetchAll(), fn(User $u) => $u->id !== $me->id));
		$me->household = null;
		$this->orm->users->persist($me);
		if (!$others) {
			// last member — the household and all its shared data go away
			$this->orm->households->remove($hh);
		} elseif ($hh->owner->id === $me->id) {
			$hh->owner = $others[0];
			$this->orm->households->persist($hh);
		}
		$this->orm->flush();
		$this->flashMessage('Opustil(a) jsi domácnost.', 'success');
		$this->redirect('default');
	}


	protected function createComponentCreateForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Název domácnosti')->setRequired('Pojmenuj domácnost.')->setHtmlAttribute('placeholder', 'Např. Byt Vinohrady');
		$form->addSubmit('send', 'Založit domácnost');
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$me = $this->getCurrentUser();
			if ($me->household !== null) {
				$form->addError('Už jsi členem domácnosti.');
				return;
			}
			$hh = new Household;
			$hh->name = $data->name;
			$hh->owner = $me;
			$hh->inviteCode = $this->generateCode();
			$this->orm->households->persist($hh);
			$me->household = $hh;
			$this->orm->users->persistAndFlush($me);
			$this->flashMessage('Domácnost založena. Pozvi ostatní kódem nebo odkazem.', 'success');
			$this->redirect('default');
		};
		return $form;
	}


	protected function createComponentJoinForm(): Form
	{
		$form = new Form;
		$form->addText('code', 'Kód pozvánky')->setRequired('Zadej kód.')->setHtmlAttribute('placeholder', 'ABC123')
			->setHtmlAttribute('autocomplete', 'off')->setHtmlAttribute('class', 'uppercase tracking-[0.3em] font-mono');
		$form->addSubmit('send', 'Připojit se');
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$this->joinByCode($data->code);
			$this->redirect('default');
		};
		return $form;
	}


	protected function createComponentRenameForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Název')->setRequired()->setDefaultValue($this->household?->name);
		$form->addSubmit('send', 'Uložit');
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$this->assertOwner();
			$this->household->name = $data->name;
			$this->orm->households->persistAndFlush($this->household);
			$this->flashMessage('Uloženo.', 'success');
			$this->redirect('this');
		};
		return $form;
	}


	// ---------- helpers ----------

	private function joinByCode(string $code): void
	{
		$me = $this->getCurrentUser();
		$hh = $this->orm->households->getBy(['inviteCode' => strtoupper(trim($code))]);
		if ($hh === null) {
			$this->flashMessage('Neplatný kód pozvánky.', 'error');
		} elseif ($me->household !== null && $me->household->id === $hh->id) {
			$this->flashMessage('V této domácnosti už jsi.', 'success');
		} elseif ($me->household !== null) {
			$this->flashMessage('Už jsi v jiné domácnosti. Nejdřív ji opusť v nastavení.', 'error');
		} else {
			$me->household = $hh;
			$this->orm->users->persistAndFlush($me);
			$this->flashMessage("Vítej v domácnosti {$hh->name}!", 'success');
		}
	}


	/** @return array<string, float> */
	private function getMonthlyByCategory(): array
	{
		$now = new \DateTimeImmutable;
		$rows = $this->orm->householdExpenses->findBy([
			'household' => $this->household,
			'date>=' => $now->modify('first day of this month')->setTime(0, 0),
			'date<=' => $now->modify('last day of this month')->setTime(23, 59, 59),
		]);
		$sums = [];
		foreach ($rows as $e) {
			$sums[$e->category] = ($sums[$e->category] ?? 0) + $e->amount;
		}
		return $sums;
	}


	private function generateCode(): string
	{
		do {
			$code = Random::generate(6, 'A-HJ-NP-Z2-9'); // no 0/O/1/I — easy to retype
		} while ($this->orm->households->getBy(['inviteCode' => $code]) !== null);
		return $code;
	}


	private function loadMember(int $id): User
	{
		$u = $this->orm->users->getById($id);
		if ($u === null || $u->household?->id !== $this->household->id) {
			throw new ForbiddenRequestException('Není členem domácnosti.');
		}
		return $u;
	}


	private function loadItem(int $id): HouseholdItem
	{
		$item = $this->orm->householdItems->getById($id) ?? throw new BadRequestException;
		$this->assertInHousehold($item->household);
		return $item;
	}


	private function assertInHousehold(Household $hh): void
	{
		if ($this->household === null || $hh->id !== $this->household->id) {
			throw new ForbiddenRequestException;
		}
	}


	private function assertOwner(): void
	{
		if ($this->household->owner->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Jen pro správce domácnosti.');
		}
	}
}
