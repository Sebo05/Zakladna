<?php declare(strict_types=1);

namespace App\Model\Notification;

use DateTimeImmutable;
use Latte\Engine;
use Nette\Mail\Mailer;
use Nette\Mail\Message;
use Nextras\Dbal\Connection;
use App\Model\Orm;
use App\Model\User\User;
use App\Model\CalendarEvent\CalendarEvent;


/**
 * Daily e-mail digest: car documents expiring (30 / 7 / 1 day before), tomorrow's events,
 * recurring payments due within 3 days. Each reminder is sent once (notification_log).
 * Run from cron: php bin/cron.php
 */
final class Notifier
{
	private const DocThresholds = [1, 7, 30];
	private const RecurringAheadDays = 3;


	public function __construct(
		private readonly string $appUrl,
		private readonly string $mailFrom,
		private readonly string $tempDir,
		private readonly Orm $orm,
		private readonly Connection $db,
		private readonly Mailer $mailer,
	) {
	}


	/** @return int number of e-mails sent */
	public function run(): int
	{
		$sent = 0;
		foreach ($this->orm->users->findBy(['notifyEmail' => true]) as $user) {
			$items = $this->collect($user);
			$items = array_filter($items, fn($i) => !$this->db->query('SELECT 1 FROM notification_log WHERE user_id = %i AND ref = %s', $user->id, $i['ref'])->fetch());
			if (!$items) {
				continue;
			}
			$this->send($user, array_values($items));
			foreach ($items as $i) {
				$this->db->query('INSERT IGNORE INTO notification_log %values', ['user_id' => $user->id, 'ref' => $i['ref'], 'sent_at' => new DateTimeImmutable]);
			}
			$sent++;
		}
		return $sent;
	}


	/** @return list<array{ref: string, icon: string, title: string, when: string, days: int}> */
	public function collect(User $user): array
	{
		$today = new DateTimeImmutable('today');
		$days = fn(DateTimeImmutable $d) => (int) $today->diff($d->setTime(0, 0))->format('%r%a');
		$items = [];

		foreach ($user->cars as $car) {
			foreach ($car->documents as $doc) {
				$left = $days($doc->expiresAt);
				$bucket = current(array_filter(self::DocThresholds, fn($t) => $left >= 0 && $left <= $t));
				if ($bucket === false) {
					continue;
				}
				$items[] = [
					'ref' => "doc:{$doc->id}:{$doc->expiresAt->format('Y-m-d')}:$bucket",
					'icon' => '🚗',
					'title' => $doc::TYPE_LABELS[$doc->type] . ' · ' . $car->name,
					'when' => $doc->expiresAt->format('j. n. Y'),
					'days' => $left,
				];
			}
		}

		$tomorrow = $today->modify('+1 day');
		foreach ($this->orm->calendarEvents->findBy(['user' => $user, 'date>=' => $tomorrow, 'date<' => $tomorrow->modify('+1 day'), 'source' => CalendarEvent::SOURCE_MANUAL]) as $e) {
			$items[] = ['ref' => "event:{$e->id}:{$e->date->format('Y-m-d')}", 'icon' => '📅', 'title' => $e->title, 'when' => 'zítra', 'days' => 1];
		}

		foreach ($this->orm->recurringTransactions->findDue($user, $today->modify('+' . self::RecurringAheadDays . ' days')) as $r) {
			$items[] = [
				'ref' => "rec:{$r->id}:{$r->nextDate->format('Y-m-d')}",
				'icon' => $r->type === $r::TYPE_INCOME ? '💰' : '💸',
				'title' => $r->description . ' · ' . number_format($r->amount, 0, ',', ' ') . ' Kč',
				'when' => $r->nextDate->format('j. n. Y'),
				'days' => $days($r->nextDate),
			];
		}

		usort($items, fn($a, $b) => $a['days'] <=> $b['days']);
		return $items;
	}


	private function send(User $user, array $items): void
	{
		$latte = new Engine;
		$latte->setTempDirectory($this->tempDir . '/cache/latte');
		$first = $items[0];
		$subject = count($items) === 1
			? $first['title'] . ($first['days'] <= 0 ? ' — dnes' : " — za {$first['days']} " . ($first['days'] === 1 ? 'den' : ($first['days'] < 5 ? 'dny' : 'dní')))
			: count($items) . ' věcí, které se blíží';

		$mail = (new Message)
			->setFrom($this->mailFrom)
			->addTo($user->email, $user->name)
			->setSubject($subject)
			->setHtmlBody($latte->renderToString(__DIR__ . '/digest.latte', [
				'user' => $user,
				'items' => $items,
				'appUrl' => rtrim($this->appUrl, '/'),
			]));
		$this->mailer->send($mail);
	}
}
