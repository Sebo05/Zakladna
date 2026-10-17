<?php declare(strict_types=1);

namespace App\Model\Page;

use DateTimeImmutable;
use Nextras\Orm\Entity\Entity;


/**
 * @property int $id {primary}
 * @property string $title
 * @property string $slug
 * @property string|null $content
 * @property bool $visible {default true}
 * @property DateTimeImmutable $createdAt {default now}
 */
final class Page extends Entity
{
}
