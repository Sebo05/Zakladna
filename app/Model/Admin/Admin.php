<?php declare(strict_types=1);

namespace App\Model\Admin;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;


/**
 * @property int $id {primary}
 * @property string $name
 * @property string $email
 * @property string $password
 * @property DateTimeImmutable $createdAt {default now}
 */
final class Admin extends Entity
{
}
