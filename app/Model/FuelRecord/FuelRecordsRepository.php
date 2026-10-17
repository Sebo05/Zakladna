<?php declare(strict_types=1);

namespace App\Model\FuelRecord;

use Nextras\Orm\Repository\Repository;


/**
 * @extends Repository<FuelRecord>
 */
final class FuelRecordsRepository extends Repository
{
	public static function getEntityClassNames(): array
	{
		return [FuelRecord::class];
	}


	/**
	 * Consumption via the full-tank method: liters filled since the previous full tank
	 * divided by km driven since then. Fuel before the first full tank is unknown, so it's skipped.
	 *
	 * @param iterable<object{id: int, mileage: int, liters: float, priceTotal: float, fullTank: bool}> $records
	 * @return array{perRecord: array<int, float>, avg: ?float, costPerKm: ?float, km: int, liters: float, spent: float}
	 */
	public static function computeStats(iterable $records): array
	{
		$list = is_array($records) ? $records : iterator_to_array($records, false);
		usort($list, fn($a, $b) => $a->mileage <=> $b->mileage);

		$perRecord = [];
		$lastFull = null;
		$litersSince = $spentSince = 0.0;
		$measuredKm = 0;
		$measuredLiters = $measuredSpent = 0.0;
		$liters = $spent = 0.0;

		foreach ($list as $r) {
			$liters += $r->liters;
			$spent += $r->priceTotal;
			if ($lastFull !== null) {
				$litersSince += $r->liters;
				$spentSince += $r->priceTotal;
			}
			if (!$r->fullTank) {
				continue;
			}
			if ($lastFull !== null && $r->mileage > $lastFull->mileage) {
				$km = $r->mileage - $lastFull->mileage;
				$perRecord[$r->id] = $litersSince / $km * 100;
				$measuredKm += $km;
				$measuredLiters += $litersSince;
				$measuredSpent += $spentSince;
			}
			$lastFull = $r;
			$litersSince = $spentSince = 0.0;
		}

		$km = $list ? end($list)->mileage - reset($list)->mileage : 0;
		return [
			'perRecord' => $perRecord,
			'avg' => $measuredKm > 0 ? $measuredLiters / $measuredKm * 100 : null,
			'costPerKm' => $measuredKm > 0 ? $measuredSpent / $measuredKm : null,
			'km' => $km,
			'liters' => $liters,
			'spent' => $spent,
		];
	}
}
