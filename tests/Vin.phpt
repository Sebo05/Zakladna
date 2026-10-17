<?php declare(strict_types=1);

use App\Model\Car\Vin;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
Tester\Environment::setup();

Assert::same(['brand' => 'BMW i', 'year' => 2024], Vin::decode('WBY81EH09RCS34231', 2026)); // BMW docs sample, R = 2024
Assert::same(['brand' => 'Škoda', 'year' => 2019], Vin::decode('TMBJJ7NE5K0123456', 2026));
Assert::same(['brand' => 'Toyota', 'year' => 2009], Vin::decode('JTDKB20U093123456', 2026)); // 2-char WMI, 9 = 2009
Assert::same(['brand' => null, 'year' => 2010], Vin::decode('XXX11111XA1234567', 2026));
Assert::same(['brand' => 'Volkswagen', 'year' => 2027], Vin::decode('WVWZZZ1KZVW000001', 2026)); // next model year allowed
Assert::same(['brand' => null, 'year' => null], Vin::decode('WVWZZZ1KZIW00000', 2026)); // invalid
Assert::false(Vin::isValid('WVWZZZ1KZOW000001')); // O not allowed
