<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;


/** One movement on the account as the bank reports it. Amount is signed: + incoming, − outgoing. */
final readonly class BankMovement
{
	public function __construct(
		public string $id,
		public DateTimeImmutable $date,
		public float $amount,
		public string $currency,
		public ?string $counterName,
		public ?string $message,
	) {
	}


	/** Human readable title for the transaction list. */
	public function title(): string
	{
		$title = trim(implode(' · ', array_filter([$this->counterName, $this->message])));
		return mb_substr($title !== '' ? $title : 'Bankovní pohyb', 0, 255);
	}
}
