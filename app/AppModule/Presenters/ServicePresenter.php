<?php declare(strict_types=1);

namespace App\Presentation\App\Service;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\Car\Car;
use App\Model\ServiceRecord\ServiceRecord;


final class ServicePresenter extends BasePresenter
{
	private ?Car $car = null;
	private ?ServiceRecord $record = null;


	public function actionAdd(int $id): void
	{
		$this->car = $this->loadOwnedCar($id);
		$this['serviceForm']->setDefaults(['date' => date('Y-m-d')]);
	}


	public function renderAdd(int $id): void
	{
		$this->template->car = $this->car;
	}


	public function actionEdit(int $id): void
	{
		$this->record = $this->orm->serviceRecords->getById($id);
		if ($this->record === null) {
			throw new BadRequestException('Záznam nenalezen.');
		}
		$this->assertOwns($this->record->car);
		$this->car = $this->record->car;
		$this['serviceForm']->setDefaults([
			'date' => $this->record->date->format('Y-m-d'),
			'mileage' => $this->record->mileage,
			'description' => $this->record->description,
			'cost' => $this->record->cost,
			'shop' => $this->record->shop,
		]);
	}


	public function renderEdit(int $id): void
	{
		$this->template->car = $this->car;
		$this->template->record = $this->record;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$record = $this->orm->serviceRecords->getById($id);
		if ($record === null) {
			throw new BadRequestException('Záznam nenalezen.');
		}
		$this->assertOwns($record->car);
		$carId = $record->car->id;
		$this->orm->serviceRecords->removeAndFlush($record);
		$this->flashMessage('Servisní záznam smazán.', 'success');
		$this->redirect(':App:Garage:detail', ['id' => $carId]);
	}


	protected function createComponentServiceForm(): Form
	{
		$form = new Form;
		$form->addDate('date', 'Datum')->setFormat('Y-m-d')->setRequired();
		$form->addInteger('mileage', 'Stav km')->addRule($form::Range, 'Zadej hodnotu mezi %d a %d.', [0, 2000000000])->setRequired();
		$form->addTextArea('description', 'Popis')->setRequired()->setHtmlAttribute('rows', 4);
		$form->addFloat('cost', 'Cena (Kč)')->addRule($form::Range, 'Zadej částku mezi %d a %d.', [0, 99999999])->setDefaultValue(0);
		$form->addText('shop', 'Servis (volitelné)');
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$record = $this->record ?? new ServiceRecord;
			if ($this->record === null) {
				$record->car = $this->car;
			}
			$record->date = new \DateTimeImmutable($data->date);
			$record->mileage = $data->mileage;
			$record->description = $data->description;
			$record->cost = $data->cost;
			$record->shop = $data->shop !== '' ? $data->shop : null;
			$this->orm->serviceRecords->persistAndFlush($record);
			$this->flashMessage($this->record ? 'Záznam uložen.' : 'Záznam přidán.', 'success');
			$this->redirect(':App:Garage:detail', ['id' => $record->car->id]);
		};
		return $form;
	}

}
