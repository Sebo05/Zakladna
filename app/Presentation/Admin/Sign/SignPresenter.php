<?php declare(strict_types=1);

namespace App\Presentation\Admin\Sign;

use Nette;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use App\Security\AdminAuthenticator;


final class SignPresenter extends Presenter
{
	#[Nette\Application\Attributes\Persistent]
	public string $backlink = '';


	public function __construct(
		private AdminAuthenticator $authenticator,
	) {
		parent::__construct();
	}


	protected function startup(): void
	{
		parent::startup();
		$this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow');
	}


	protected function createComponentSignInForm(): Form
	{
		$form = new Form;
		$form->addEmail('email', 'E-mail:')
			->setRequired('Zadejte e-mail.');

		$form->addPassword('password', 'Heslo:')
			->setRequired('Zadejte heslo.');

		$form->addSubmit('send', 'Přihlásit');

		$form->onSuccess[] = $this->signInFormSucceeded(...);
		return $form;
	}


	private function signInFormSucceeded(Form $form, \stdClass $data): void
	{
		try {
			$user = $this->getUser();
			$user->setAuthenticator($this->authenticator);
			$user->login($data->email, $data->password);
			$this->restoreRequest($this->backlink);
			$this->redirect(':Admin:Dashboard:default');
		} catch (Nette\Security\AuthenticationException $e) {
			$form->addError('Neplatné přihlašovací údaje.');
		}
	}


	public function actionOut(): void
	{
		$this->getUser()->logout();
		$this->flashMessage('Byli jste odhlášeni.');
		$this->redirect('in');
	}
}
