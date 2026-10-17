<?php declare(strict_types=1);

namespace App\Presentation\Frontend\Auth;

use Nette;
use Nette\Application\UI\Form;
use Nette\Security\Passwords;
use App\Presentation\Frontend\BasePresenter;
use App\Model\Bank\BankException;
use App\Security\GoogleAuth;
use App\Security\UserAuthenticator;


final class AuthPresenter extends BasePresenter
{
	/** Key from storeRequest(); after login the user returns to the page they wanted. */
	#[Nette\Application\Attributes\Persistent]
	public string $backlink = '';


	public function __construct(
		private Passwords $passwords,
		private UserAuthenticator $authenticator,
		private GoogleAuth $google,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();
		$this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, follow');
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


	/** Sign in with Google: without ?code → off to Google; Google comes back here with ?code&state. */
	public function actionGoogle(?string $code = null, ?string $state = null, ?string $error = null): void
	{
		if (!$this->google->isEnabled()) {
			$this->error();
		}
		$session = $this->getSession('googleAuth');
		$redirectUri = $this->link('//google', ['backlink' => '']); // must equal the URI registered at Google

		if ($code === null && $error === null) {
			$session->state = bin2hex(random_bytes(16));
			$session->backlink = $this->backlink;
			$this->redirectUrl($this->google->authorizeUrl($redirectUri, $session->state));
		}

		$expected = $session->state;
		$this->backlink = (string) $session->backlink;
		unset($session->state, $session->backlink);
		if ($error !== null || $expected === null || !hash_equals($expected, (string) $state)) {
			$this->flashMessage('Přihlášení přes Google se nepovedlo, zkus to znovu.', 'error');
			$this->redirect('login');
		}

		try {
			$profile = $this->google->profile((string) $code, $redirectUri);
		} catch (BankException) {
			$profile = null;
		}
		if ($profile === null) {
			$this->flashMessage('Přihlášení přes Google se nepovedlo (e-mail účtu není ověřený nebo Google neodpověděl).', 'error');
			$this->redirect('login');
		}

		$row = $this->orm->users->getBy(['googleId' => $profile['sub']]) ?? $this->orm->users->findByEmail($profile['email']);
		$new = $row === null;
		if ($new) {
			// random password nobody knows — e-mail login works once they set one in the profile
			$row = $this->orm->users->register($profile['name'], $profile['email'], bin2hex(random_bytes(32)), $this->passwords);
		}
		$row->googleId = $profile['sub']; // links an existing e-mail account on the first Google sign-in
		$this->orm->users->persistAndFlush($row);

		$this->getUser()->setExpiration('14 days');
		$this->getUser()->login(UserAuthenticator::identity($row));
		$this->flashMessage($new ? 'Vítej v Základně!' : 'Vítej zpět!', 'success');
		$this->redirectAfterLogin();
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->googleEnabled = $this->google->isEnabled();
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
