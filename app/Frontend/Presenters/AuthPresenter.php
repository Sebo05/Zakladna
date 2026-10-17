<?php declare(strict_types=1);

namespace App\Presentation\Frontend\Auth;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;
use App\Presentation\Frontend\BasePresenter;
use App\FrontendModule\Authenticator;


final class AuthPresenter extends BasePresenter
{
	/** Key from storeRequest(); after login the user returns to the page they wanted. */
	#[Nette\Application\Attributes\Persistent]
	public string $backlink = '';


	public function __construct(
		private Passwords $passwords,
		private Authenticator $authenticator,
	) {
		parent::__construct();
	}


	public function actionLogin(): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->redirectAfterLogin();
		}
	}


	public function actionRegister(): void
	{
		if ($this->getUser()->isLoggedIn()) {
			$this->redirectAfterLogin();
		}
	}


	public function actionLogout(): void
	{
		$this->getUser()->logout(true);
		$this->flashMessage('Byli jste odhlášeni.', 'success');
		$this->redirect(':Frontend:Home:default');
	}


	protected function createComponentLoginForm(): Form
	{
		$form = new Form;
		$this->addEmailInput($form);
		$form->addPassword('password', 'Heslo')
			->setHtmlAttribute(':type', "show ? 'text' : 'password'")
			->setHtmlAttribute('autocomplete', 'current-password')
			->setRequired('Zadej heslo.');
		$form->addCheckbox('remember', 'Zapamatovat si mě');
		$form->addSubmit('send', 'Přihlásit se');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			try {
				$user = $this->getUser();
				$user->setAuthenticator($this->authenticator);
				// remembered: 14 days of inactivity, otherwise 20 min (needs session.expiration >= 14 days in config)
				$user->setExpiration($data->remember ? '14 days' : '20 minutes');
				$user->login($data->email, $data->password);
				$this->flashMessage('Vítej zpět!', 'success');
				$this->redirectAfterLogin();
			} catch (Nette\Security\AuthenticationException) {
				$form->addError('Neplatný e-mail nebo heslo.');
			}
		};
		return $form;
	}


	protected function createComponentRegisterForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Jméno')
			->setRequired('Zadej jméno.')
			->setMaxLength(100)
			->setHtmlAttribute('autocomplete', 'given-name');
		$this->addEmailInput($form);
		$form->addPassword('password', 'Heslo')
			->setHtmlAttribute(':type', "show ? 'text' : 'password'")
			->setHtmlAttribute('x-model', 'p')
			->setHtmlAttribute('autocomplete', 'new-password')
			->setRequired('Zadej heslo.')
			->addRule($form::MinLength, 'Heslo musí mít alespoň %d znaků.', 6);
		$form->addPassword('passwordConfirm', 'Heslo znovu')
			->setHtmlAttribute(':type', "show ? 'text' : 'password'")
			->setHtmlAttribute('x-model', 'p2')
			->setHtmlAttribute('autocomplete', 'new-password')
			->setRequired('Zopakuj heslo.')
			->addRule($form::Equal, 'Hesla se neshodují.', $form['password']);
		$form->addSubmit('send', 'Vytvořit účet');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			if ($this->orm->users->findByEmail($data->email) !== null) {
				$form['email']->addError('Tento e-mail už je zaregistrovaný. Zkus se přihlásit.');
				return;
			}
			$this->orm->users->register($data->name, $data->email, $data->password, $this->passwords);
			$user = $this->getUser();
			$user->setAuthenticator($this->authenticator);
			$user->setExpiration('14 days');
			$user->login($data->email, $data->password);
			$this->flashMessage('Vítej v Základně!', 'success');
			$this->redirectAfterLogin();
		};
		return $form;
	}


	/** Text input with type=email so the validation message is ours (addEmail() brings an English default). */
	private function addEmailInput(Form $form): void
	{
		$form->addText('email', 'E-mail')
			->setHtmlType('email')
			->setHtmlAttribute('autocomplete', 'email')
			->setMaxLength(150)
			->setRequired('Zadej e-mail.')
			->addRule($form::Email, 'Tohle nevypadá jako e-mail.');
	}


	private function redirectAfterLogin(): void
	{
		if ($this->backlink !== '') {
			$this->restoreRequest($this->backlink); // no-op when the key is unknown/expired
		}
		$this->redirect(':App:Dashboard:default');
	}
}
