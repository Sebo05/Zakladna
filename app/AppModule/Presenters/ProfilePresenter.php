<?php declare(strict_types=1);

namespace App\Presentation\App\Profile;

use Nette\Application\Responses\CallbackResponse;
use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Nette\Http\IResponse;
use Nette\Security\Passwords;
use App\Presentation\App\BasePresenter;
use App\Model\User\User;


final class ProfilePresenter extends BasePresenter
{
	/** @inject */
	public Passwords $passwords;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$this->template->carsCount = $user->cars->countStored();
		$this->template->txCount = $user->transactions->countStored();
		$this->template->baseUrl = rtrim($this->getHttpRequest()->getUrl()->getBaseUrl(), '/');
	}


	/** CSV of the user's transactions; UTF-8 BOM + ';' so Excel opens it correctly. */
	public function actionExport(): void
	{
		$user = $this->getCurrentUser();
		$rows = $this->orm->transactions->findBy(['user' => $user])->orderBy('date', 'DESC');
		$this->getHttpResponse()->setContentType('text/csv', 'utf-8');
		$this->getHttpResponse()->setHeader('Content-Disposition', 'attachment; filename="zakladna-transakce-' . date('Y-m-d') . '.csv"');
		$this->sendResponse(new CallbackResponse(function (IRequest $req, IResponse $res) use ($rows): void {
			$out = fopen('php://output', 'w');
			fwrite($out, "\xEF\xBB\xBF");
			fputcsv($out, ['Datum', 'Typ', 'Částka', 'Kategorie', 'Popis', 'Auto'], ';');
			foreach ($rows as $t) {
				fputcsv($out, [
					$t->date->format('Y-m-d'),
					$t->type === 'income' ? 'příjem' : 'výdaj',
					number_format($t->amount, 2, ',', ''),
					$t->category,
					$t->description,
					$t->car?->name ?? $t->carName ?? '',
				], ';');
			}
			fclose($out);
		}));
	}


	protected function createComponentProfileForm(): Form
	{
		$user = $this->getCurrentUser();
		$form = new Form;
		$form->addText('name', 'Jméno')->setRequired('Zadej jméno.')->setMaxLength(100);
		$form->addText('email', 'E-mail')
			->setHtmlType('email')
			->setRequired('Zadej e-mail.')
			->addRule($form::Email, 'Zadej platný e-mail.')
			->setMaxLength(150);
		$form->addSubmit('send', 'Uložit změny');
		$form->setDefaults(['name' => $user->name, 'email' => $user->email]);

		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($user): void {
			$other = $this->orm->users->findByEmail($data->email);
			if ($other !== null && $other->id !== $user->id) {
				$form['email']->addError('Tento e-mail už používá jiný účet.');
				return;
			}
			$user->name = $data->name;
			$user->email = $data->email;
			$this->orm->users->persistAndFlush($user);
			$this->flashMessage('Údaje uloženy.', 'success');
			$this->redirect('this');
		};
		return $form;
	}


	protected function createComponentPasswordForm(): Form
	{
		$toggle = "show ? 'text' : 'password'";
		$form = new Form;
		$form->addPassword('current', 'Současné heslo')
			->setRequired('Zadej současné heslo.')
			->setHtmlAttribute(':type', $toggle)
			->setHtmlAttribute('autocomplete', 'current-password');
		$form->addPassword('password', 'Nové heslo')
			->setRequired('Zadej nové heslo.')
			->addRule($form::MinLength, 'Heslo musí mít alespoň %d znaků.', 8)
			->setHtmlAttribute(':type', $toggle)
			->setHtmlAttribute('autocomplete', 'new-password');
		$form->addPassword('passwordConfirm', 'Nové heslo znovu')
			->setRequired('Potvrď nové heslo.')
			->addRule($form::Equal, 'Hesla se neshodují.', $form['password'])
			->setHtmlAttribute(':type', $toggle)
			->setHtmlAttribute('autocomplete', 'new-password');
		$form->addSubmit('send', 'Změnit heslo');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$user = $this->getCurrentUser();
			if (!$this->passwords->verify($data->current, $user->password)) {
				$form['current']->addError('Současné heslo nesedí.');
				return;
			}
			$user->password = $this->passwords->hash($data->password);
			$this->orm->users->persistAndFlush($user);
			$this->flashMessage('Heslo změněno.', 'success');
			$this->redirect('this');
		};
		return $form;
	}


	protected function createComponentDeleteForm(): Form
	{
		$form = new Form;
		$form->addText('confirmEmail', 'Pro potvrzení napiš svůj e-mail')
			->setRequired('Napiš svůj e-mail.')
			->setHtmlAttribute('autocomplete', 'off');
		$form->addPassword('password', 'Heslo')
			->setRequired('Zadej heslo.')
			->setHtmlAttribute('autocomplete', 'current-password');
		$form->addSubmit('send', 'Trvale smazat účet');

		// Nette 3.2 forms reject cross-origin POSTs by default, so this is CSRF-safe.
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$user = $this->getCurrentUser();
			if (mb_strtolower(trim($data->confirmEmail)) !== mb_strtolower($user->email)) {
				$form['confirmEmail']->addError('E-mail nesouhlasí.');
				return;
			}
			if (!$this->passwords->verify($data->password, $user->password)) {
				$form['password']->addError('Špatné heslo.');
				return;
			}
			$this->deleteAccount($user);
			$this->getUser()->logout(true);
			$this->flashMessage('Účet byl smazán. Díky, že jsi byl(a) s námi.', 'success');
			$this->redirect(':Frontend:Home:default');
		};
		return $form;
	}


	private function deleteAccount(User $user): void
	{
		// Leave the household: hand ownership over, or drop it when last member.
		$hh = $user->household;
		if ($hh !== null) {
			$others = array_values(array_filter($hh->members->toCollection()->fetchAll(), fn(User $u) => $u->id !== $user->id));
			$user->household = null;
			$this->orm->users->persist($user);
			if (!$others) {
				$this->orm->households->remove($hh);
			} elseif ($hh->owner->id === $user->id) {
				$hh->owner = $others[0];
				$this->orm->households->persist($hh);
			}
			$this->orm->flush();
		}

		// Rows in shared household data whose FKs to users don't cascade.
		$orm = $this->orm;
		foreach ($orm->householdExpenseShares->findBy(['user' => $user]) as $r) {
			$orm->householdExpenseShares->remove($r);
		}
		foreach ($orm->householdExpenses->findBy(['paidBy' => $user]) as $r) {
			$orm->householdExpenses->remove($r);
		}
		foreach ($orm->householdSettlements->findBy(['fromUser' => $user]) as $r) {
			$orm->householdSettlements->remove($r);
		}
		foreach ($orm->householdSettlements->findBy(['toUser' => $user]) as $r) {
			$orm->householdSettlements->remove($r);
		}
		foreach ($orm->householdItems->findBy(['createdBy' => $user]) as $r) {
			$orm->householdItems->remove($r);
		}
		foreach ($orm->goalDeposits->findBy(['user' => $user]) as $r) {
			$orm->goalDeposits->remove($r);
		}
		$orm->flush();

		foreach ($user->cars as $car) {
			if ($car->photo && is_file($p = __DIR__ . '/../../../www/upload/cars/' . basename($car->photo))) {
				@unlink($p);
			}
		}

		// ORM cascades cars (+ service, documents, fuel), transactions, events, budgets, goals.
		$orm->users->removeAndFlush($user);
	}
}
