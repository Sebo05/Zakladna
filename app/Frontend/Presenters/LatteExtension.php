<?php declare(strict_types=1);

namespace App\Presentation\Accessory;

use DateTimeInterface;
use Latte\Extension;


final class LatteExtension extends Extension
{
	private const Months = ['leden', 'únor', 'březen', 'duben', 'květen', 'červen', 'červenec', 'srpen', 'září', 'říjen', 'listopad', 'prosinec'];
	private const MonthsGen = ['ledna', 'února', 'března', 'dubna', 'května', 'června', 'července', 'srpna', 'září', 'října', 'listopadu', 'prosince'];
	private const Days = ['pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'];
	private const DaysShort = ['po', 'út', 'st', 'čt', 'pá', 'so', 'ne'];


	public function getFilters(): array
	{
		return [
			// "říjen"
			'czMonth' => fn(DateTimeInterface $d): string => self::Months[(int) $d->format('n') - 1],
			// "pátek 17. října"
			'czLongDate' => fn(DateTimeInterface $d): string => self::Days[(int) $d->format('N') - 1] . ' ' . $d->format('j') . '. ' . self::MonthsGen[(int) $d->format('n') - 1],
			// "pá 17. 10."
			'czShortDate' => fn(DateTimeInterface $d): string => self::DaysShort[(int) $d->format('N') - 1] . ' ' . $d->format('j. n.'),
			// whole days from today (negative = past)
			'daysUntil' => fn(DateTimeInterface $d): int => (int) (new \DateTimeImmutable('today'))->diff(\DateTimeImmutable::createFromInterface($d)->setTime(0, 0))->format('%r%a'),
			// "1 234 Kč"
			'czk' => fn(float|int $v, int $decimals = 0): string => number_format($v, $decimals, ',', ' ') . ' Kč',
		];
	}


	public function getFunctions(): array
	{
		return [];
	}
}
