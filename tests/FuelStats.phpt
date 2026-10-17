<?php declare(strict_types=1);

use App\Model\FuelRecord\FuelRecordsRepository;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
Tester\Environment::setup();

$r = fn(int $id, int $km, float $l, float $price, bool $full = true) => (object) [
	'id' => $id, 'mileage' => $km, 'liters' => $l, 'priceTotal' => $price, 'fullTank' => $full,
];

// first full tank is the baseline, no consumption yet
$s = FuelRecordsRepository::computeStats([$r(1, 10000, 40, 1600)]);
Assert::same([], $s['perRecord']);
Assert::null($s['avg']);

// 600 km on 36 l → 6 l/100 km; partial fill is added to the next full tank's interval
$s = FuelRecordsRepository::computeStats([
	$r(3, 11000, 30, 1200),           // full: 20 l partial + 30 l over 400 km = 12.5
	$r(1, 10000, 40, 1600),           // baseline (unsorted input on purpose)
	$r(2, 10600, 36, 1440),           // full: 36 l / 600 km = 6
	$r(4, 10800, 20, 800, false),
]);
Assert::equal(6.0, $s['perRecord'][2]);
Assert::equal(12.5, $s['perRecord'][3]);
Assert::false(isset($s['perRecord'][4]));
Assert::equal(86 / 1000 * 100, $s['avg']);
Assert::equal((1440 + 800 + 1200) / 1000, $s['costPerKm']);
Assert::same(1000, $s['km']);
Assert::equal(126.0, $s['liters']);
