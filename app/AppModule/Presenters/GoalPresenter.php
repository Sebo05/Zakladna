<?php declare(strict_types=1);

namespace App\Presentation\App\Goal;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;
use App\Presentation\App\BasePresenter;
use App\Model\Goal\Goal;
use App\Model\Goal\GoalDeposit;


final class GoalPresenter extends BasePresenter
{
	private ?Goal $goal = null;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$goals = $this->orm->goals->findBy(['user' => $user])->orderBy('createdAt')->fetchAll();
		if ($user->household !== null) {
			$goals = array_merge($goals, $this->orm->goals->findBy(['household' => $user->household])->orderBy('createdAt')->fetchAll());
		}
		$totalTarget = $totalSaved = 0.0;
		foreach ($goals as $g) {
			$totalTarget += $g->targetAmount;
			$totalSaved += min($g->saved, $g->targetAmount);
		}
		$this->template->goals = $goals;
		$this->template->totalTarget = $totalTarget;
		$this->template->totalSaved = $totalSaved;
	}


	public function actionEdit(int $id): void
	{
		$this->goal = $this->loadGoal($id);
		$this['goalForm']->setDefaults([
			'name' => $this->goal->name,
			'targetAmount' => $this->goal->targetAmount,
			'deadline' => $this->goal->deadline?->format('Y-m-d'),
			'color' => $this->goal->color,
			'shared' => $this->goal->household !== null,
		]);
	}


	public function renderEdit(): void
	{
		$this->template->goal = $this->goal;
	}


	#[Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$this->orm->goals->removeAndFlush($this->loadGoal($id));
		$this->flashMessage('Cíl smazán.', 'success');
		$this->redirect('default');
	}


	#[Requires(sameOrigin: true)]
	public function actionDeleteDeposit(int $id): void
	{
		$deposit = $this->orm->goalDeposits->getById($id) ?? throw new BadRequestException;
		if (!$deposit->goal->canAccess($this->getCurrentUser())) {
			throw new ForbiddenRequestException;
		}
		$this->orm->goalDeposits->removeAndFlush($deposit);
		$this->flashMessage('Vklad odebrán.', 'success');
		$this->redirect('default');
	}


	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->colors = Goal::COLORS;
		$this->template->hasHousehold = $this->getCurrentUser()->household !== null;
	}


	protected function createComponentGoalForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Na co spoříš')
			->setRequired('Pojmenuj cíl.')
			->setHtmlAttribute('placeholder', 'Dovolená, nové auto, rezerva…');
		$form->addFloat('targetAmount', 'Cílová částka (Kč)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 9999999999)
			->setRequired('Zadej cílovou částku.')
			->addRule($form::Min, 'Částka musí být větší než 0.', 1);
		$form->addDate('deadline', 'Termín (volitelné)')->setFormat('Y-m-d');
		$form->addRadioList('color', 'Barva', array_combine(Goal::COLORS, Goal::COLORS))->setDefaultValue('blue')->setRequired();
		$form->addCheckbox('shared', 'Společný cíl domácnosti');
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$user = $this->getCurrentUser();
			$goal = $this->goal ?? new Goal;
			$shared = $data->shared && $user->household !== null;
			$goal->user = $shared ? null : $user;
			$goal->household = $shared ? $user->household : null;
			$goal->name = $data->name;
			$goal->targetAmount = $data->targetAmount;
			$goal->deadline = $data->deadline ? new \DateTimeImmutable($data->deadline) : null;
			$goal->color = $data->color;
			$this->orm->goals->persistAndFlush($goal);
			$this->flashMessage('Cíl uložen.', 'success');
			$this->redirect('default');
		};
		return $form;
	}


	/** One small deposit form per goal: {form "depositForm-$id"} */
	protected function createComponentDepositForm(): Multiplier
	{
		return new Multiplier(function (string $goalId): Form {
			$form = new Form;
			$form->addFloat('amount')->addRule($form::Max, 'Hodnota je příliš vysoká.', 9999999999)
				->setRequired('Zadej částku.')
				->addRule($form::Min, 'Částka musí být větší než 0.', 0.01)
				->setHtmlAttribute('placeholder', 'Částka')
				->setHtmlAttribute('x-ref', 'amount');
			$form->addCheckbox('withdraw');
			$form->addSubmit('send', 'Vložit');

			$form->onSuccess[] = function (Form $form, \stdClass $data) use ($goalId): void {
				$goal = $this->loadGoal((int) $goalId);
				$wasReached = $goal->saved >= $goal->targetAmount;
				if ($data->withdraw && $data->amount > $goal->saved) {
					$this->flashMessage('Nelze vybrat víc, než je naspořeno.', 'error');
					$this->redirect('default');
				}
				$deposit = new GoalDeposit;
				$deposit->goal = $goal;
				$deposit->user = $this->getCurrentUser();
				$deposit->amount = $data->withdraw ? -$data->amount : $data->amount;
				$deposit->date = new \DateTimeImmutable;
				$this->orm->goalDeposits->persistAndFlush($deposit);

				if (!$wasReached && $goal->saved >= $goal->targetAmount) {
					$this->flashMessage("Cíl „{$goal->name}“ je splněný! Gratulujeme.", 'success');
				} else {
					$this->flashMessage($data->withdraw ? 'Vybráno z cíle.' : 'Vloženo na cíl.', 'success');
				}
				$this->redirect('default');
			};
			return $form;
		});
	}


	private function loadGoal(int $id): Goal
	{
		$goal = $this->orm->goals->getById($id) ?? throw new BadRequestException('Cíl nenalezen.');
		if (!$goal->canAccess($this->getCurrentUser())) {
			throw new ForbiddenRequestException('Cizí cíl.');
		}
		return $goal;
	}
}
