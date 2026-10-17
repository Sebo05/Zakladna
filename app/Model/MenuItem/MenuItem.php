<?php declare(strict_types=1);

namespace App\Model\MenuItem;

use Nextras\Orm\Entity\Entity;


/**
 * @property int $id {primary}
 * @property string $label
 * @property string $url
 * @property int $position {default 0}
 * @property bool $visible {default true}
 */
final class MenuItem extends Entity
{
}
