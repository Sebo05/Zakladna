<?php declare(strict_types=1);

namespace App\Presentation\App\Calendar;

use Nette;
use Nette\Application\BadRequestException;
use Nette\Application\ForbiddenRequestException;
use Nette\Application\UI\Form;
use App\Presentation\App\BasePresenter;
use App\Model\CalendarEvent\CalendarEvent;
use App\Model\CarDocument\CarDocument;


final class CalendarPresenter extends BasePresenter
{
	private ?CalendarEvent $event = null;

	#[Nette\Application\Attributes\Persistent]
	public ?string $month = null;


	public function renderDefault(): void
	{
		$user = $this->getCurrentUser();
		$now = new \DateTimeImmutable;
		$today = $now->setTime(0, 0);

		$cursor = preg_match('~^\d{4}-(0[1-9]|1[0-2])$~', (string) $this->month)
			? new \DateTimeImmutable($this->month . '-01')
			: $now;
		$monthStart = $cursor->modify('first day of this month')->setTime(0, 0);
		$monthEnd = $cursor->modify('last day of this month')->setTime(23, 59, 59);

		// Manual events for the visible month
		$manualMonth = $this->orm->calendarEvents
			->findBy(['user' => $user, 'date>=' => $monthStart, 'date<=' => $monthEnd])
			->orderBy('date');

		// CarDocument-derived events for the visible month
		$carIds = [];
		foreach ($user->cars as $car) {
			$carIds[] = $car->id;
		}
		$docsMonth = $carIds
			? $this->orm->carDocuments
				->findBy(['car' => $carIds, 'expiresAt>=' => $monthStart, 'expiresAt<=' => $monthEnd])
				->orderBy('expiresAt')
			: [];

		// Build day → events[] map for the grid
		$byDay = [];
		foreach ($manualMonth as $e) {
			$byDay[$e->date->format('Y-m-d')][] = [
				'kind' => 'manual',
				'id' => $e->id,
				'title' => $e->title,
				'description' => $e->description,
			];
		}
		foreach ($docsMonth as $d) {
			$byDay[$d->expiresAt->format('Y-m-d')][] = [
				'kind' => 'garage',
				'id' => $d->id,
				'title' => CarDocument::TYPE_LABELS[$d->type] . ' · ' . $d->car->name,
				'description' => $d->note,
			];
		}

		// Build grid (6 weeks × 7 days), starting Monday
		$gridStart = $monthStart;
		$weekday = (int) $gridStart->format('N'); // 1 = Mon
		$gridStart = $gridStart->modify('-' . ($weekday - 1) . ' days');
		$days = [];
		for ($i = 0; $i < 42; $i++) {
			$d = $gridStart->modify("+$i days");
			$key = $d->format('Y-m-d');
			$days[] = [
				'date' => $d,
				'inMonth' => $d->format('Y-m') === $monthStart->format('Y-m'),
				'isToday' => $key === $today->format('Y-m-d'),
				'events' => $byDay[$key] ?? [],
			];
		}

		// Upcoming list (next 30 days) — manual + garage
		$listEnd = $today->modify('+30 days');
		$upcomingManual = $this->orm->calendarEvents
			->findBy(['user' => $user, 'date>=' => $today, 'date<=' => $listEnd])
			->orderBy('date');
		$upcomingDocs = $carIds
			? $this->orm->carDocuments
				->findBy(['car' => $carIds, 'expiresAt>=' => $today, 'expiresAt<=' => $listEnd])
				->orderBy('expiresAt')
			: [];

		$upcoming = [];
		foreach ($upcomingManual as $e) {
			$upcoming[] = ['date' => $e->date, 'kind' => 'manual', 'id' => $e->id, 'title' => $e->title, 'description' => $e->description];
		}
		foreach ($upcomingDocs as $d) {
			$upcoming[] = ['date' => $d->expiresAt, 'kind' => 'garage', 'id' => $d->id, 'title' => CarDocument::TYPE_LABELS[$d->type] . ' · ' . $d->car->name, 'description' => $d->note];
		}
		usort($upcoming, fn($a, $b) => $a['date'] <=> $b['date']);

		$this->template->days = $days;
		$this->template->upcoming = $upcoming;
		$this->template->monthLabel = $monthStart;
		$this->template->prevMonth = $monthStart->modify('-1 month')->format('Y-m');
		$this->template->nextMonth = $monthStart->modify('+1 month')->format('Y-m');
		$this->template->today = $today;
	}


	public function actionAdd(): void
	{
		$this['eventForm']->setDefaults(['date' => date('Y-m-d')]);
	}


	public function actionEdit(int $id): void
	{
		$this->event = $this->loadOwnedEvent($id);
		$this['eventForm']->setDefaults([
			'title' => $this->event->title,
			'date' => $this->event->date->format('Y-m-d'),
			'description' => $this->event->description,
		]);
	}


	public function renderEdit(int $id): void
	{
		$this->template->event = $this->event;
	}


	#[\Nette\Application\Attributes\Requires(sameOrigin: true)]
	public function actionDelete(int $id): void
	{
		$event = $this->loadOwnedEvent($id);
		$this->orm->calendarEvents->removeAndFlush($event);
		$this->flashMessage('Událost smazána.', 'success');
		$this->redirect('default');
	}


	protected function createComponentEventForm(): Form
	{
		$form = new Form;
		$form->addText('title', 'Název')->setRequired();
		$form->addDate('date', 'Datum')->setFormat('Y-m-d')->setRequired();
		$form->addTextArea('description', 'Popis')->setHtmlAttribute('rows', 4);
		$form->addSubmit('send', 'Uložit');

		$form->onSuccess[] = function (Form $form, \stdClass $data): void {
			$event = $this->event ?? new CalendarEvent;
			if ($this->event === null) {
				$event->user = $this->getCurrentUser();
				$event->source = CalendarEvent::SOURCE_MANUAL;
			}
			$event->title = $data->title;
			$event->date = new \DateTimeImmutable($data->date);
			$event->description = $data->description !== '' ? $data->description : null;
			$this->orm->calendarEvents->persistAndFlush($event);
			$this->flashMessage($this->event ? 'Událost uložena.' : 'Událost přidána.', 'success');
			$this->redirect('default');
		};
		return $form;
	}


	private function loadOwnedEvent(int $id): CalendarEvent
	{
		$event = $this->orm->calendarEvents->getById($id);
		if ($event === null) {
			throw new BadRequestException('Událost nenalezena.');
		}
		if ($event->user->id !== $this->getCurrentUser()->id) {
			throw new ForbiddenRequestException('Cizí událost.');
		}
		return $event;
	}
}
