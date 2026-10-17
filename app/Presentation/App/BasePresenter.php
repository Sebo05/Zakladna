<?php declare(strict_types=1);

namespace App\Presentation\App;

use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Presenter;
use App\Model\Orm;
use App\Model\Car\Car;
use App\Model\User\User;


abstract class BasePresenter extends Presenter
{
	/** @inject */
	public Orm $orm;


	public function checkRequirements(mixed $element): void
	{
		// private area — keep it out of search engines (also disallowed in robots.txt)
		$this->getHttpResponse()->setHeader('X-Robots-Tag', 'noindex, nofollow');
		parent::checkRequirements($element);

		// Logged in as admin — admin and user sessions are separate.
		// Log them out so they can sign in as a regular user.
		if ($this->getUser()->isLoggedIn() && !$this->getUser()->isInRole('user')) {
			$this->getUser()->logout(true);
			$this->flashMessage('Pro přístup do aplikace se přihlas jako uživatel.', 'error');
			$this->redirect(':Frontend:Auth:login');
		}

		if (!$this->getUser()->isLoggedIn()) {
			$this->redirect(':Frontend:Auth:login', ['backlink' => $this->storeRequest()]);
		}
	}


	protected function getCurrentUser(): User
	{
		$id = (int) $this->getUser()->getId();
		$user = $this->orm->users->getById($id);
		if ($user === null) {
			$this->getUser()->logout(true);
			$this->redirect(':Frontend:Auth:login');
		}
		return $user;
	}


	protected function loadOwnedCar(int $id): Car
	{
		$car = $this->orm->cars->getById($id);
		if ($car === null) {
			throw new BadRequestException('Auto nenalezeno.');
		}
		$this->assertOwns($car);
		return $car;
	}


	protected function assertOwns(Car $car): void
	{
		if ($car->user->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Cizí auto.');
		}
	}


	protected function beforeRender(): void
	{
		parent::beforeRender();
		$user = $this->getCurrentUser();
		$this->template->currentUser = $user;
		$this->template->paletteActions = $this->buildPaletteActions($user);
	}


	/** Static entries of the Ctrl+K palette; full-text results come from SearchPresenter. */
	private function buildPaletteActions(User $user): array
	{
		$item = fn(string $group, string $title, string $dest, array $args, string $icon, string $keywords = '') => [
			'group' => $group,
			'title' => $title,
			'url' => $this->link($dest, $args),
			'icon' => $icon,
			'keywords' => $keywords,
		];

		$items = [
			$item('Přidat', 'Nový výdaj', ':App:Finance:add', [], 'ArrowUpRight', 'transakce platba utrata nakup'),
			$item('Přidat', 'Nový příjem', ':App:Finance:add', ['type' => 'income'], 'ArrowDownLeft', 'transakce vyplata'),
		];
		foreach ($user->cars as $car) {
			$items[] = $item('Přidat', 'Tankování · ' . $car->name, ':App:Fuel:add', ['id' => $car->id], 'Fuel', 'benzin nafta palivo pumpa');
			$items[] = $item('Přidat', 'Servis · ' . $car->name, ':App:Service:add', ['id' => $car->id], 'Wrench', 'oprava udrzba');
		}
		$items[] = $item('Přidat', 'Nová událost', ':App:Calendar:add', [], 'CalendarDays', 'termin kalendar');
		$items[] = $item('Přidat', 'Nové auto', ':App:Garage:add', [], 'Car', 'vozidlo garaz');
		$items[] = $item('Přidat', 'Nový rozpočet', ':App:Budget:add', [], 'PiggyBank', 'limit');
		$items[] = $item('Přidat', 'Opakovaná platba', ':App:Recurring:add', [], 'Repeat', 'najem netflix leasing pravidelna');
		$items[] = $item('Přidat', 'Nový spořicí cíl', ':App:Goal:add', [], 'Target', 'sporeni cil');
		if ($user->household !== null) {
			$items[] = $item('Přidat', 'Společný výdaj', ':App:Household:expenses', [], 'Receipt', 'domacnost vyrovnani');
			$items[] = $item('Přidat', 'Nákupní seznam', ':App:Household:lists', [], 'ShoppingCart', 'nakup ukol');
		}

		$items[] = $item('Přejít', 'Přehled', ':App:Dashboard:default', [], 'House', 'dashboard domu');
		$items[] = $item('Přejít', 'Garáž', ':App:Garage:default', [], 'Car', 'auta');
		$items[] = $item('Přejít', 'Finance', ':App:Finance:default', [], 'Wallet', 'transakce');
		$items[] = $item('Přejít', 'Rozpočty', ':App:Budget:default', [], 'PiggyBank', 'limity');
		$items[] = $item('Přejít', 'Opakované platby', ':App:Recurring:default', [], 'Repeat', 'najem predplatne');
		$items[] = $item('Přejít', 'Banka', ':App:Bank:default', [], 'Landmark', 'fio sporitelna import synchronizace');
		$items[] = $item('Přejít', 'Cíle', ':App:Goal:default', [], 'Target', 'sporeni');
		$items[] = $item('Přejít', 'Domácnost', ':App:Household:default', [], 'HousePlus', 'spolubydlici rodina');
		$items[] = $item('Přejít', 'Statistiky', ':App:Stats:default', [], 'ChartPie', 'grafy');
		$items[] = $item('Přejít', 'Nastavení profilu', ':App:Profile:default', [], 'Settings', 'ucet heslo email export');
		$items[] = $item('Přejít', 'Kalendář', ':App:Calendar:default', [], 'Calendar', 'udalosti');
		foreach ($user->cars as $car) {
			$items[] = $item('Přejít', $car->name, ':App:Garage:detail', ['id' => $car->id], 'Car', $car->licensePlate);
		}
		return $items;
	}
}
