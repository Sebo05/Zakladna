<?php declare(strict_types=1);

use App\Model\Household\Ledger;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
Tester\Environment::setup();

// equal split sums exactly, remainder to the first
$s = Ledger::splitEqually(100.0, [1, 2, 3]);
Assert::same([1 => 33.34, 2 => 33.33, 3 => 33.33], $s);
Assert::equal(100.0, round(array_sum($s), 2));

// A pays 300 for all three, B pays 60 for A+B
$bal = Ledger::balances([1, 2, 3], [
	['paidBy' => 1, 'amount' => 300.0, 'shares' => [1 => 100.0, 2 => 100.0, 3 => 100.0]],
	['paidBy' => 2, 'amount' => 60.0, 'shares' => [1 => 30.0, 2 => 30.0]],
], []);
Assert::same([1 => 170.0, 2 => -70.0, 3 => -100.0], $bal);
Assert::equal(0.0, round(array_sum($bal), 2));

$pay = Ledger::simplify($bal);
Assert::same([
	['from' => 3, 'to' => 1, 'amount' => 100.0],
	['from' => 2, 'to' => 1, 'amount' => 70.0],
], $pay);

// after C settles 100 with A, only B → A remains
$bal = Ledger::balances([1, 2, 3], [
	['paidBy' => 1, 'amount' => 300.0, 'shares' => [1 => 100.0, 2 => 100.0, 3 => 100.0]],
	['paidBy' => 2, 'amount' => 60.0, 'shares' => [1 => 30.0, 2 => 30.0]],
], [['from' => 3, 'to' => 1, 'amount' => 100.0]]);
Assert::same([['from' => 2, 'to' => 1, 'amount' => 70.0]], Ledger::simplify($bal));
