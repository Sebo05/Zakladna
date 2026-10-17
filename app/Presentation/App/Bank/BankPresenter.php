<?php declare(strict_types=1);

namespace App\Presentation\App\Bank;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use Nette\Utils\Random;
use App\Presentation\App\BasePresenter;
use App\Model\Bank\BankAccount;
use App\Model\Bank\BankException;
use App\Model\Bank\BankSyncService;
use App\Model\Bank\SecretService;


/** Bank connections: Fio (API token), ČS + KB (OAuth2), ČSOB + RB (client certificate). Movements are imported as transactions. */
final class BankPresenter extends BasePresenter
{
	/** @inject */
	public BankSyncService $sync;

	/** @inject */
	public SecretService $secrets;


	public function renderDefault(): void
	{
		$this->template->accounts = $this->orm->bankAccounts->findBy(['user' => $this->getCurrentUser()])->orderBy('createdAt')->fetchAll();
		$this->template->callbackUrl = $this->link('//callback');
		$this->template->uncategorized = $this->orm->transactions->findBy(['user' => $this->getCurrentUser(), 'category' => BankSyncService::Uncategorized])->countStored();
	}


	#[Requires(sameOrigin: true)]
	public function handleSync(int $id): void
	{
		$this->syncAndFlash($this->loadOwned($id));
		$this->redirect('this');
	}


	#[Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$account = $this->loadOwned($id);
		if ($account->certFile) {
			@unlink(BankAccount::CERT_DIR . '/' . $account->certFile);
		}
		$this->orm->bankAccounts->removeAndFlush($account);
		$this->flashMessage('Banka odpojena. Importované transakce zůstaly.', 'success');
		$this->redirect('default');
	}


	/** ČS / KB: send the user to the bank to grant access. */
	public function actionConnect(int $id): void
	{
		$account = $this->loadOwned($id);
		if (!$account->usesOAuth()) {
			throw new BadRequestException;
		}
		$state = Random::generate(24);
		$this->getSession('bank')->set('oauth', ['state' => $state, 'id' => $account->id]);
		$this->redirectUrl($this->sync->oauth($account)->authorizationUrl($account, $this->link('//callback'), $state));
	}


	/** ČS / KB OAuth2 redirect target — the bank is taken from the account remembered in actionConnect. */
	public function actionCallback(?string $code = null, ?string $state = null, ?string $error = null): void
	{
		$oauth = $this->getSession('bank')->get('oauth');
		$this->getSession('bank')->remove('oauth');
		if (!$oauth || !$state || !hash_equals($oauth['state'], $state)) {
			$this->flashMessage('Připojení vypršelo, zkus to znovu.', 'error');
			$this->redirect('default');
		}
		$account = $this->loadOwned($oauth['id']);
		if ($error || !$code) {
			$this->flashMessage('Banka přístup nepovolila' . ($error ? " ($error)" : '') . '.', 'error');
			$this->redirect('default');
		}

		try {
			$api = $this->sync->oauth($account);
			$api->exchangeCode($account, $code, $this->link('//callback'));
			$this->orm->bankAccounts->persistAndFlush($account);
			$list = $api->accounts($account);
		} catch (BankException $e) {
			$this->flashMessage($e->getMessage(), 'error');
			$this->redirect('default');
		}

		if (count($list) === 1) {
			$account->accountRef = $list[0]['id'];
			$this->orm->bankAccounts->persistAndFlush($account);
			$this->syncAndFlash($account);
			$this->redirect('default');
		}
		$this->getSession('bank')->set('choose', ['id' => $account->id, 'list' => $list]);
		$this->redirect('choose');
	}


	public function renderChoose(): void
	{
		$choose = $this->getSession('bank')->get('choose') ?? $this->redirect('default');
		$this->template->list = $choose['list'];
	}


	#[Requires(sameOrigin: true)]
	public function handleChoose(string $ref): void
	{
		$choose = $this->getSession('bank')->get('choose') ?? $this->redirect('default');
		if (!in_array($ref, array_column($choose['list'], 'id'), true)) {
			throw new BadRequestException;
		}
		$account = $this->loadOwned($choose['id']);
		$account->accountRef = $ref;
		$this->orm->bankAccounts->persistAndFlush($account);
		$this->getSession('bank')->remove('choose');
		$this->syncAndFlash($account);
		$this->redirect('default');
	}


	protected function createComponentFioForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Název')->setDefaultValue('Fio účet')->setRequired()->setMaxLength(100);
		$form->addPassword('token', 'API token')
			->setRequired('Vlož token z internetbankingu.')
			->addRule($form::Pattern, 'Token má 64 znaků (písmena a čísla).', '[A-Za-z0-9]{64}')
			->setHtmlAttribute('autocomplete', 'off');
		$form->addSubmit('send', 'Připojit Fio');
		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$account = new BankAccount;
			$account->user = $this->getCurrentUser();
			$account->bank = BankAccount::BANK_FIO;
			$account->name = $data->name;
			$account->secret = $this->secrets->encrypt($data->token);
			$this->orm->bankAccounts->persistAndFlush($account);
			$this->syncAndFlash($account);
			$this->redirect('default');
		};
		return $form;
	}


	protected function createComponentCsForm(): Form
	{
		return $this->oauthForm(BankAccount::BANK_CS, 'Spořitelna', 'WEB-API-key');
	}


	protected function createComponentKbForm(): Form
	{
		return $this->oauthForm(BankAccount::BANK_KB, 'Komerční banka', 'apiKey');
	}


	protected function createComponentCsobForm(): Form
	{
		$form = $this->certForm(BankAccount::BANK_CSOB, 'ČSOB');
		$form->addText('clientId', 'Číslo smlouvy CEB')->setRequired()->setMaxLength(20)
			->addRule($form::Pattern, 'Číslo smlouvy obsahuje jen číslice.', '\d+');
		$form->addText('accountRef', 'Číslo účtu (nepovinné)')->setMaxLength(50);
		return $form;
	}


	protected function createComponentRbForm(): Form
	{
		$form = $this->certForm(BankAccount::BANK_RB, 'Raiffeisenbank');
		$form->addText('clientId', 'Client ID (X-IBM-Client-Id)')->setRequired()->setMaxLength(255)->setHtmlAttribute('autocomplete', 'off');
		$form->addText('accountRef', 'Číslo účtu')->setRequired()->setMaxLength(50)
			->addRule($form::Pattern, 'Číslo účtu bez kódu banky, např. 1234567890.', '[\d-]+');
		return $form;
	}


	private function oauthForm(string $bank, string $name, string $apiKeyLabel): Form
	{
		$form = new Form;
		$form->addText('name', 'Název')->setDefaultValue($name)->setRequired()->setMaxLength(100);
		$form->addText('clientId', 'client_id')->setRequired()->setMaxLength(255)->setHtmlAttribute('autocomplete', 'off');
		$form->addPassword('clientSecret', 'client_secret')->setRequired()->setHtmlAttribute('autocomplete', 'off');
		$form->addPassword('apiKey', $apiKeyLabel)->setRequired()->setHtmlAttribute('autocomplete', 'off');
		$form->addCheckbox('sandbox', 'Sandbox (testovací data z developer portálu)');
		$form->addSubmit('send', 'Uložit a připojit');
		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($bank): void {
			$account = new BankAccount;
			$account->user = $this->getCurrentUser();
			$account->bank = $bank;
			$account->name = $data->name;
			$account->clientId = $data->clientId;
			$account->clientSecret = $this->secrets->encrypt($data->clientSecret);
			$account->apiKey = $this->secrets->encrypt($data->apiKey);
			$account->sandbox = $data->sandbox;
			$this->orm->bankAccounts->persistAndFlush($account);
			$this->redirect('connect', $account->id);
		};
		return $form;
	}


	/** ČSOB / RB: certificate goes to cert/bank/ (outside www/, denied by .htaccess, not in git), password encrypted. */
	private function certForm(string $bank, string $name): Form
	{
		$form = new Form;
		$form->addText('name', 'Název')->setDefaultValue($name)->setRequired()->setMaxLength(100);
		$form->addUpload('cert', 'Klientský certifikát (.p12, .pfx, .pem)')
			->setRequired('Nahraj certifikát.')
			->addRule($form::MaxFileSize, 'Certifikát může mít max. 64 kB.', 64 * 1024);
		$form->addPassword('certPassword', 'Heslo k certifikátu')->setHtmlAttribute('autocomplete', 'new-password');
		$form->addCheckbox('sandbox', 'Testovací prostředí banky');
		$form->addSubmit('send', 'Připojit ' . $name);
		$form->onValidate[] = function (Form $form): void {
			$upload = $form['cert']->getValue();
			if ($upload->isOk() && !in_array(strtolower(pathinfo($upload->getUntrustedName(), PATHINFO_EXTENSION)), ['p12', 'pfx', 'pem'], true)) {
				$form['cert']->addError('Certifikát musí být soubor .p12, .pfx nebo .pem.');
			}
		};
		$form->onSuccess[] = function (Form $form, \stdClass $data) use ($bank): void {
			$file = $this->getCurrentUser()->id . '-' . Random::generate(16) . '.' . strtolower(pathinfo($data->cert->getUntrustedName(), PATHINFO_EXTENSION));
			$data->cert->move(BankAccount::CERT_DIR . '/' . $file);
			$account = new BankAccount;
			$account->user = $this->getCurrentUser();
			$account->bank = $bank;
			$account->name = $data->name;
			$account->clientId = trim($data->clientId);
			$account->accountRef = trim($data->accountRef) ?: null;
			$account->certFile = $file;
			$account->certPassword = $this->secrets->encrypt($data->certPassword);
			$account->sandbox = $data->sandbox;
			$this->orm->bankAccounts->persistAndFlush($account);
			$this->syncAndFlash($account);
			$this->redirect('default');
		};
		return $form;
	}


	private function syncAndFlash(BankAccount $account): void
	{
		try {
			$n = $this->sync->sync($account);
			$this->flashMessage($n ? "Staženo $n nových pohybů." : 'Žádné nové pohyby.', 'success');
		} catch (BankException $e) {
			$this->flashMessage($e->getMessage(), 'error');
		}
	}


	private function loadOwned(int $id): BankAccount
	{
		$a = $this->orm->bankAccounts->getById($id) ?? throw new BadRequestException('Účet nenalezen.');
		if ($a->user->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Cizí účet.');
		}
		return $a;
	}
}
