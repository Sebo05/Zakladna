<?php declare(strict_types=1);

namespace App\Presentation\App\Fuel;

use Nette\Application\Attributes\Requires;
use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\Car\Car;
use App\Model\FuelRecord\FuelRecord;
use App\Model\Transaction\Transaction;


/** Refuels. Each one is mirrored as an expense transaction (category "Palivo"). */
final class FuelPresenter extends BasePresenter
{
	private ?Car $car = null;
	private ?FuelRecord $record = null;


	public function actionAdd(int $id): void
	{
		$this->car = $this->loadOwnedCar($id);
		$this['fuelForm']->setDefaults([
			'date' => date('Y-m-d'),
			'fullTank' => true,
		]);
	}


	public function actionEdit(int $id): void
	{
		$this->record = $this->orm->fuelRecords->getById($id) ?? throw new BadRequestException('Záznam nenalezen.');
		$this->assertOwns($this->record->car);
		$this->car = $this->record->car;
		$this['fuelForm']->setDefaults([
			'date' => $this->record->date->format('Y-m-d'),
			'mileage' => $this->record->mileage,
			'liters' => $this->record->liters,
			'priceTotal' => $this->record->priceTotal,
			'fullTank' => $this->record->fullTank,
			'station' => $this->record->station,
		]);
	}


	#[Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$record = $this->orm->fuelRecords->getById($id) ?? throw new BadRequestException('Záznam nenalezen.');
		$this->assertOwns($record->car);
		$carId = $record->car->id;
		$tx = $record->transaction;
		$this->orm->fuelRecords->remove($record);
		if ($tx !== null) {
			$this->orm->transactions->remove($tx);
		}
		$this->orm->flush();
		$this->flashMessage('Tankování smazáno.', 'success');
		$this->redirect('Garage:detail', $carId);
	}


	public function beforeRender(): void
	{
		parent::beforeRender();
		$this->template->car = $this->car;
		$this->template->record = $this->record;
		$this->template->lastMileage = $this->getLastMileage($this->car);
	}


	protected function createComponentFuelForm(): Form
	{
		$form = new Form;
		$form->addDate('date', 'Datum')->setFormat('Y-m-d')->setRequired('Zadej datum.');
		$form->addInteger('mileage', 'Stav tachometru (km)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 2000000000)
			->setRequired('Zadej stav tachometru.')
			->addRule($form::Min, 'Stav km nemůže být záporný.', 0);
		$form->addFloat('liters', 'Natankováno (l)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 999999)
			->setRequired('Zadej počet litrů.')
			->addRule($form::Min, 'Musí být víc než 0.', 0.01);
		$form->addFloat('priceTotal', 'Zaplaceno (Kč)')->addRule($form::Max, 'Hodnota je příliš vysoká.', 99999999)
			->setRequired('Zadej cenu.')
			->addRule($form::Min, 'Musí být víc než 0.', 0.01);
		$form->addCheckbox('fullTank', 'Plná nádrž');
		$form->addText('station', 'Čerpací stanice')->setNullable();
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$record = $this->record ?? new FuelRecord;
			if ($this->record === null) {
				$record->car = $this->car;
			}
			$record->date = new \DateTimeImmutable($data->date);
			$record->mileage = $data->mileage;
			$record->liters = $data->liters;
			$record->priceTotal = $data->priceTotal;
			$record->fullTank = $data->fullTank;
			$record->station = $data->station;

			$tx = $record->transaction ?? new Transaction;
			$tx->user = $this->car->user;
			$tx->type = Transaction::TYPE_EXPENSE;
			$tx->amount = $data->priceTotal;
			$tx->description = 'Tankování' . ($data->station ? ' · ' . $data->station : '');
			$tx->category = FuelRecord::CATEGORY;
			$tx->car = $this->car;
			$tx->carName = $this->car->name;
			$tx->date = $record->date;
			$record->transaction = $tx;

			$this->orm->persistAndFlush($record);
			$this->flashMessage($this->record ? 'Tankování uloženo.' : 'Tankování přidáno.', 'success');
			$this->redirect('Garage:detail', $this->car->id);
		};
		return $form;
	}


	private function getLastMileage(Car $car): ?int
	{
		$m = null;
		foreach ($car->fuelRecords as $r) {
			$m = max($m ?? 0, $r->mileage);
		}
		foreach ($car->serviceRecords as $r) {
			$m = max($m ?? 0, $r->mileage);
		}
		return $m;
	}
}
