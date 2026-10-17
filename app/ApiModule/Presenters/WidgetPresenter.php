<?php declare(strict_types=1);

namespace App\Presentation\Api\Widget;

use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;
use App\FrontendModule\Authenticator;
use App\Model\Orm;
use App\Model\Transaction\Transaction;
use App\Model\CarDocument\CarDocument;


/**
 * JSON summary for the desktop widget. Auth: HTTP Basic (user e-mail + password), no session.
 */
final class WidgetPresenter extends Presenter
{
	public function __construct(
		private Orm $orm,
		private Authenticator $authenticator,
	) {
		parent::__construct();
	}


	public function actionDefault(): void
	{
		[$email, $password] = $this->getBasicCredentials();
		try {
			$userId = $this->authenticator->authenticate($email, $password)->getId();
		} catch (AuthenticationException) {
			$this->getHttpResponse()->setCode(401);
			$this->sendJson(['error' => 'Neplatný e-mail nebo heslo.']);
		}
		$user = $this->orm->users->getById($userId);

		$now = new \DateTimeImmutable;
		$today = $now->setTime(0, 0);

		$income = $expense = 0.0;
		$monthTxs = $this->orm->transactions->findBy([
			'user' => $user,
			'date>=' => $now->modify('first day of this month')->setTime(0, 0),
			'date<=' => $now->modify('last day of this month')->setTime(23, 59, 59),
		]);
		foreach ($monthTxs as $tx) {
			if ($tx->type === Transaction::TYPE_INCOME) {
				$income += $tx->amount;
			} else {
				$expense += $tx->amount;
			}
		}

		$carIds = [];
		foreach ($user->cars as $car) {
			$carIds[] = $car->id;
		}
		$docs = [];
		if ($carIds) {
			$rows = $this->orm->carDocuments
				->findBy(['car' => $carIds, 'expiresAt<=' => $today->modify('+60 days')])
				->orderBy('expiresAt');
			foreach ($rows as $d) {
				$docs[] = [
					'type' => $d->type,
					'title' => CarDocument::TYPE_LABELS[$d->type] . ' · ' . $d->car->name,
					'date' => $d->expiresAt->format('Y-m-d'),
					'days' => (int) $today->diff($d->expiresAt->setTime(0, 0))->format('%r%a'),
				];
			}
		}

		$events = [];
		$rows = $this->orm->calendarEvents
			->findBy(['user' => $user, 'date>=' => $today, 'date<=' => $today->modify('+7 days')])
			->orderBy('date');
		foreach ($rows as $e) {
			$events[] = ['title' => $e->title, 'date' => $e->date->format('Y-m-d')];
		}

		$this->sendJson([
			'user' => $user->name,
			'month' => $now->format('Y-m'),
			'income' => $income,
			'expense' => $expense,
			'balance' => $income - $expense,
			'documents' => $docs,
			'events' => $events,
			'appUrl' => $this->link('//:App:Dashboard:default'),
		]);
	}


	/** @return array{string, string} */
	private function getBasicCredentials(): array
	{
		$header = (string) $this->getHttpRequest()->getHeader('Authorization');
		if (preg_match('~^Basic\s+(.+)$~i', $header, $m)) {
			$decoded = (string) base64_decode($m[1], true);
			if (str_contains($decoded, ':')) {
				return explode(':', $decoded, 2);
			}
		}
		$this->getHttpResponse()->setCode(401);
		$this->sendJson(['error' => 'Chybí přihlašovací údaje.']);
	}
}
