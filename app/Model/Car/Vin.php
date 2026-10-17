<?php declare(strict_types=1);

namespace App\Model\Car;


/**
 * Offline VIN decode (ISO 3779): brand from the WMI (first 3 chars), model year from char 10.
 * ponytail: WMI list covers brands common in CZ; unknown → null brand. Add rows when someone misses one.
 * European makers don't always encode the year — callers treat it as a hint only.
 */
final class Vin
{
	/** longest prefix wins */
	private const Brands = [
		'TMB' => 'Škoda', 'TMA' => 'Hyundai', 'TMP' => 'Škoda',
		'WBA' => 'BMW', 'WBS' => 'BMW M', 'WBY' => 'BMW i', 'WBX' => 'BMW', '5UX' => 'BMW', '4US' => 'BMW', 'WB1' => 'BMW Motorrad', 'WMW' => 'MINI',
		'WDB' => 'Mercedes-Benz', 'WDC' => 'Mercedes-Benz', 'WDD' => 'Mercedes-Benz', 'WDF' => 'Mercedes-Benz', 'W1K' => 'Mercedes-Benz', 'W1N' => 'Mercedes-Benz', 'W1V' => 'Mercedes-Benz',
		'WVW' => 'Volkswagen', 'WVG' => 'Volkswagen', 'WV1' => 'Volkswagen', 'WV2' => 'Volkswagen', 'LSV' => 'Volkswagen',
		'WAU' => 'Audi', 'WUA' => 'Audi', 'TRU' => 'Audi', 'WP0' => 'Porsche', 'WP1' => 'Porsche',
		'VSS' => 'SEAT', 'VSE' => 'SEAT', 'W0L' => 'Opel', 'W0V' => 'Opel', 'WF0' => 'Ford', 'WF1' => 'Ford',
		'VF1' => 'Renault', 'UU1' => 'Dacia', 'VF3' => 'Peugeot', 'VR3' => 'Peugeot', 'VF7' => 'Citroën', 'VR7' => 'Citroën', 'VR1' => 'DS',
		'ZFA' => 'Fiat', 'ZAR' => 'Alfa Romeo', 'ZFF' => 'Ferrari', 'ZHW' => 'Lamborghini', 'ZAM' => 'Maserati', 'ZLA' => 'Lancia',
		'YV1' => 'Volvo', 'YV4' => 'Volvo', 'LVY' => 'Volvo', 'YS3' => 'Saab', 'SAL' => 'Land Rover', 'SAJ' => 'Jaguar', 'SCC' => 'Lotus',
		'JT' => 'Toyota', 'SB1' => 'Toyota', 'NMT' => 'Toyota', 'VNK' => 'Toyota', 'JTH' => 'Lexus',
		'JHM' => 'Honda', 'SHH' => 'Honda', 'JN' => 'Nissan', 'SJN' => 'Nissan', 'VSK' => 'Nissan', 'JM' => 'Mazda', 'JMZ' => 'Mazda',
		'JS' => 'Suzuki', 'TSM' => 'Suzuki', 'JF' => 'Subaru', 'JMB' => 'Mitsubishi', 'JA' => 'Isuzu',
		'KMH' => 'Hyundai', 'KNA' => 'Kia', 'KNE' => 'Kia', 'U5Y' => 'Kia', 'KL' => 'Chevrolet', 'KPT' => 'SsangYong',
		'5YJ' => 'Tesla', '7SA' => 'Tesla', 'LRW' => 'Tesla', 'XP7' => 'Tesla', 'LBV' => 'BMW', 'LFV' => 'Volkswagen', 'L6T' => 'Geely', 'LGX' => 'BYD', 'LSJ' => 'MG',
		'1G' => 'General Motors', '1F' => 'Ford', '1C' => 'Chrysler', '1J' => 'Jeep', '1HG' => 'Honda',
	];

	/** char 10 → year, repeats every 30 years (I, O, Q, U, Z and 0 are not used) */
	private const YearCodes = 'ABCDEFGHJKLMNPRSTVWXY123456789';


	public static function isValid(string $vin): bool
	{
		return (bool) preg_match('~^[A-HJ-NPR-Z0-9]{17}$~', $vin);
	}


	/** @return array{brand: ?string, year: ?int} */
	public static function decode(string $vin, ?int $currentYear = null): array
	{
		if (!self::isValid($vin)) {
			return ['brand' => null, 'year' => null];
		}
		$brand = self::Brands[substr($vin, 0, 3)] ?? self::Brands[substr($vin, 0, 2)] ?? null;

		$year = null;
		$pos = strpos(self::YearCodes, $vin[9]);
		if ($pos !== false) {
			$max = ($currentYear ?? (int) date('Y')) + 1;
			$year = 1980 + $pos;
			while ($year + 30 <= $max) {
				$year += 30;
			}
		}
		return ['brand' => $brand, 'year' => $year];
	}
}
