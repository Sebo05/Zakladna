<?php declare(strict_types=1);

namespace App\Presentation\App\Garage;

use Nette\Application\BadRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\Car\Car;
use App\Model\CarDocument\CarDocument;
use App\Model\FuelRecord\FuelRecordsRepository;


final class GaragePresenter extends BasePresenter
{
	private ?Car $car = null;
	private ?CarDocument $document = null;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$cars = $this->orm->cars->findBy(['user' => $user])->orderBy('createdAt', 'DESC');

		$rows = [];
		foreach ($cars as $car) {
			$totalCost = 0.0;
			foreach ($car->serviceRecords as $sr) {
				$totalCost += $sr->cost;
			}
			$nextDoc = null;
			foreach ($car->documents as $d) {
				if ($nextDoc === null || $d->expiresAt < $nextDoc->expiresAt) {
					$nextDoc = $d;
				}
			}
			$rows[] = [
				'car' => $car,
				'totalCost' => $totalCost,
				'avgConsumption' => FuelRecordsRepository::computeStats($car->fuelRecords)['avg'],
				'nextDoc' => $nextDoc,
			];
		}
		$this->template->cars = $rows;
		$this->template->today = new \DateTimeImmutable;
		$this->template->docTypeLabels = CarDocument::TYPE_LABELS;
	}


	public function actionDetail(int $id): void
	{
		$this->car = $this->loadOwnedCar($id);
	}


	public function renderDetail(int $id): void
	{
		$this->template->car = $this->car;
		$this->template->services = $this->orm->serviceRecords
			->findBy(['car' => $this->car])
			->orderBy('date', 'DESC');
		$this->template->documents = $this->orm->carDocuments
			->findBy(['car' => $this->car])
			->orderBy('expiresAt');
		$this->template->today = new \DateTimeImmutable;
		$this->template->docTypeLabels = CarDocument::TYPE_LABELS;

		$fuel = $this->orm->fuelRecords->findBy(['car' => $this->car])->orderBy('mileage', 'DESC')->fetchAll();
		$this->template->fuel = $fuel;
		$this->template->fuelStats = FuelRecordsRepository::computeStats($fuel);
	}


	public function actionEdit(int $id): void
	{
		$this->car = $this->loadOwnedCar($id);
		$this['carForm']->setDefaults([
			'name' => $this->car->name,
			'licensePlate' => $this->car->licensePlate,
			'plateCountry' => $this->car->plateCountry,
			'vin' => $this->car->vin,
			'year' => $this->car->year,
			'purchasePrice' => $this->car->purchasePrice,
			'photo' => $this->car->photo,
		]);
	}


	public function renderEdit(int $id): void
	{
		$this->template->car = $this->car;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$car = $this->loadOwnedCar($id);

		// Detach transactions (their car field is nullable, so just null it)
		foreach ($this->orm->transactions->findBy(['car' => $car]) as $tx) {
			$tx->car = null;
			$this->orm->transactions->persist($tx);
		}

		// Remove photo from disk
		if ($car->photo && is_file($p = \App\Model\Car\Car::PhotoDir . '/' . basename($car->photo))) {
			@unlink($p);
		}

		// ServiceRecords + CarDocuments cascade automatically via entity definition
		$this->orm->cars->removeAndFlush($car);
		$this->flashMessage('Auto smazáno.', 'success');
		$this->redirect('default');
	}


	protected function createComponentCarForm(): Form
	{
		$form = new Form;
		$form->addText('name', 'Název')->setRequired();
		$form->addText('licensePlate', 'SPZ')->setRequired();
		$form->addSelect('plateCountry', 'Země', array_combine(array_keys(Car::PlateCountries), array_keys(Car::PlateCountries)))->setDefaultValue('CZ');
		$form->addText('vin', 'VIN (volitelné)')
			->setNullable()
			->addFilter(fn(?string $v) => $v === null ? null : strtoupper(preg_replace('~\s+~', '', $v)))
			->addCondition($form::Filled)
				->addRule($form::PatternInsensitive, 'VIN má 17 znaků, písmena a číslice bez I, O a Q.', '[A-HJ-NPR-Z0-9]{17}');
		$form->addInteger('year', 'Rok')
			->setRequired()
			->addRule(Form::RANGE, 'Neplatný rok.', [1900, (int) date('Y') + 1]);
		$form->addFloat('purchasePrice', 'Pořizovací cena (Kč)')->addRule($form::Range, 'Zadej částku mezi %d a %d.', [0, 9999999999])->setDefaultValue(0);
		$form->addUpload('photo', 'Fotka (volitelné)')
			->setHtmlAttribute('accept', 'image/*')
			->addRule(Form::IMAGE, 'Nahraj prosím obrázek (JPG/PNG/WebP/GIF).')
			->addRule(Form::MAX_FILE_SIZE, 'Maximální velikost je 5 MB.', 5 * 1024 * 1024);
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$car = $this->car ?? new Car;
			if ($this->car === null) {
				$car->user = $this->getCurrentUser();
			}
			$car->name = $data->name;
			$car->licensePlate = $data->licensePlate;
			$car->plateCountry = $data->plateCountry;
			$car->vin = $data->vin;
			$car->year = $data->year;
			$car->purchasePrice = $data->purchasePrice;

			/** @var \Nette\Http\FileUpload $upload */
			$upload = $data->photo;
			if ($upload->hasFile() && $upload->isOk()) {
				$ext = $upload->getSuggestedExtension() ?? 'jpg'; // from MIME, not client filename (prevents .php upload)
				$filename = 'car-' . bin2hex(random_bytes(8)) . '.' . $ext;
				$dir = \App\Model\Car\Car::PhotoDir;
				if (!is_dir($dir)) {
					mkdir($dir, 0775, true);
				}
				$upload->move($dir . '/' . $filename);
				// Remove old file if replacing
				if ($car->photo && is_file($oldPath = \App\Model\Car\Car::PhotoDir . '/' . basename($car->photo))) {
					@unlink($oldPath);
				}
				$car->photo = 'cars/' . $filename;
			}

			$this->orm->cars->persistAndFlush($car);
			$this->flashMessage($this->car ? 'Auto uloženo.' : 'Auto přidáno.', 'success');
			$this->redirect('detail', ['id' => $car->id]);
		};
		return $form;
	}


	// ---- Documents ----

	public function actionAddDocument(int $id): void
	{
		$this->car = $this->loadOwnedCar($id);
	}


	public function renderAddDocument(int $id): void
	{
		$this->template->car = $this->car;
	}


	public function actionEditDocument(int $id): void
	{
		$this->document = $this->orm->carDocuments->getById($id);
		if ($this->document === null) {
			throw new BadRequestException('Dokument nenalezen.');
		}
		$this->assertOwns($this->document->car);
		$this->car = $this->document->car;
		$this['documentForm']->setDefaults([
			'type' => $this->document->type,
			'expiresAt' => $this->document->expiresAt->format('Y-m-d'),
			'note' => $this->document->note,
		]);
	}


	public function renderEditDocument(int $id): void
	{
		$this->template->car = $this->car;
		$this->template->document = $this->document;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDeleteDocument(int $id): void
	{
		$doc = $this->orm->carDocuments->getById($id);
		if ($doc === null) {
			throw new BadRequestException('Dokument nenalezen.');
		}
		$this->assertOwns($doc->car);
		$carId = $doc->car->id;
		$this->orm->carDocuments->removeAndFlush($doc);
		$this->flashMessage('Dokument smazán.', 'success');
		$this->redirect('detail', ['id' => $carId]);
	}


	protected function createComponentDocumentForm(): Form
	{
		$form = new Form;
		$form->addSelect('type', 'Typ', CarDocument::TYPE_LABELS)->setRequired();
		$form->addDate('expiresAt', 'Platnost do')
			->setFormat('Y-m-d')
			->setRequired();
		$form->addText('note', 'Poznámka');
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$doc = $this->document ?? new CarDocument;
			if ($this->document === null) {
				$doc->car = $this->car;
			}
			$doc->type = $data->type;
			$doc->expiresAt = new \DateTimeImmutable($data->expiresAt);
			$doc->note = $data->note !== '' ? $data->note : null;
			$this->orm->carDocuments->persistAndFlush($doc);
			$this->flashMessage($this->document ? 'Dokument uložen.' : 'Dokument přidán.', 'success');
			$this->redirect('detail', ['id' => $doc->car->id]);
		};
		return $form;
	}

}
