<?php declare(strict_types=1);

namespace App\Presentation\App\Search;

use Nextras\Orm\Collection\Expression\LikeExpression;
use Nextras\Orm\Collection\ICollection;
use App\Presentation\App\BasePresenter;


/** Full-text results for the Ctrl+K palette (JSON). */
final class SearchPresenter extends BasePresenter
{
	public function actionDefault(string $q = ''): void
	{
		$q = trim($q);
		if (mb_strlen($q) < 2) {
			$this->sendJson([]);
		}
		$user = $this->getCurrentUser();
		$like = LikeExpression::contains($q);
		$out = [];

		$cars = $this->orm->cars->findBy(['user' => $user])
			->findBy([ICollection::OR, 'name~' => $like, 'licensePlate~' => $like])
			->limitBy(5);
		foreach ($cars as $c) {
			$out[] = ['group' => 'Auta', 'title' => $c->name, 'subtitle' => $c->licensePlate, 'icon' => 'Car', 'url' => $this->link('Garage:detail', $c->id)];
		}

		$txs = $this->orm->transactions->findBy(['user' => $user])
			->findBy([ICollection::OR, 'description~' => $like, 'category~' => $like])
			->orderBy('date', 'DESC')
			->limitBy(6);
		foreach ($txs as $t) {
			$sign = $t->type === 'income' ? '+' : '−';
			$out[] = [
				'group' => 'Transakce',
				'title' => $t->description,
				'subtitle' => $sign . number_format($t->amount, 0, ',', ' ') . ' Kč · ' . $t->date->format('j. n.'),
				'icon' => $t->type === 'income' ? 'ArrowDownLeft' : 'ArrowUpRight',
				'url' => $this->link('Finance:edit', $t->id),
			];
		}

		$events = $this->orm->calendarEvents->findBy(['user' => $user, 'title~' => $like])
			->orderBy('date', 'DESC')
			->limitBy(5);
		foreach ($events as $e) {
			$out[] = ['group' => 'Události', 'title' => $e->title, 'subtitle' => $e->date->format('j. n. Y'), 'icon' => 'CalendarDays', 'url' => $this->link('Calendar:edit', $e->id)];
		}

		$this->sendJson($out);
	}
}
