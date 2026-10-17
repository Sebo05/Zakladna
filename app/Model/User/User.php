<?php declare(strict_types=1);

namespace App\Model\User;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;
use Nextras\Orm\Relationships\OneHasMany;
use App\Model\Car\Car;
use App\Model\Transaction\Transaction;
use App\Model\CalendarEvent\CalendarEvent;
use App\Model\Budget\Budget;
use App\Model\Goal\Goal;
use App\Model\Household\Household;


/**
 * @property int $id {primary}
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $googleId
 * @property DateTimeImmutable $createdAt {default now}
 * @property OneHasMany<Car> $cars {1:m Car::$user, cascade=[persist, remove]}
 * @property OneHasMany<Transaction> $transactions {1:m Transaction::$user, cascade=[persist, remove]}
 * @property OneHasMany<CalendarEvent> $calendarEvents {1:m CalendarEvent::$user, cascade=[persist, remove]}
 * @property OneHasMany<Budget> $budgets {1:m Budget::$user, cascade=[persist, remove]}
 * @property OneHasMany<Goal> $goals {1:m Goal::$user, cascade=[persist, remove]}
 * @property Household|null $household {m:1 Household::$members}
 * @property string|null $dashboardLayout
 * @property bool $notifyEmail {default true}
 */
final class User extends Entity
{
}
