<?php declare(strict_types=1);

use App\Model\Bank\CsApi;
use App\Model\Bank\CsobApi;
use App\Model\Bank\KbApi;
use App\Model\Bank\RbApi;
use App\Model\Bank\FioApi;
use App\Model\Recurring\RecurringTransaction;
use Tester\Assert;

require __DIR__ . '/../vendor/autoload.php';
Tester\Environment::setup();

// Fio row (shape from fioapi transactions.json)
$m = FioApi::map([
	'column22' => ['value' => 26962199069, 'name' => 'ID pohybu', 'id' => 22],
	'column0' => ['value' => '2026-10-03+0200', 'name' => 'Datum', 'id' => 0],
	'column1' => ['value' => -1290.5, 'name' => 'Objem', 'id' => 1],
	'column14' => ['value' => 'CZK', 'name' => 'Měna', 'id' => 14],
	'column10' => ['value' => 'Albert', 'name' => 'Název protiúčtu', 'id' => 10],
	'column16' => null,
	'column7' => ['value' => 'Nákup: ALBERT 0123', 'name' => 'Uživatelská identifikace', 'id' => 7],
]);
Assert::same('fio-26962199069', $m->id);
Assert::same('2026-10-03', $m->date->format('Y-m-d'));
Assert::same(-1290.5, $m->amount);
Assert::same('Albert · Nákup: ALBERT 0123', $m->title());
Assert::null(FioApi::map(['column1' => ['value' => 10]])); // no id → skipped

// ČS camt row: debit → negative, counterparty = creditor
$c = CsApi::map([
	'entryReference' => 'ABC1',
	'amount' => ['value' => 299, 'currency' => 'CZK'],
	'creditDebitIndicator' => 'DBIT',
	'bookingDate' => ['date' => '2026-10-05'],
	'entryDetails' => ['transactionDetails' => [
		'relatedParties' => ['creditor' => ['name' => 'Netflix']],
		'remittanceInformation' => ['unstructured' => 'Předplatné'],
	]],
]);
Assert::same('cs-ABC1', $c->id);
Assert::same(-299.0, $c->amount);
Assert::same('Netflix · Předplatné', $c->title());

// KB ADAA row: DEBIT → negative, id from references.accountServicer, message from references.receiver (list of lines)
$k = KbApi::map([
	'amount' => ['value' => 1500.5, 'currency' => 'czk'],
	'creditDebitIndicator' => 'DEBIT',
	'bookingDate' => '2026-10-06',
	'references' => ['accountServicer' => 'KB123', 'variable' => '0001234', 'receiver' => ['Nájem', 'říjen']],
	'counterParty' => ['name' => 'Pronajímatel s.r.o.', 'accountNo' => '123456789', 'bankCode' => '0100'],
]);
Assert::same('kb-KB123', $k->id);
Assert::same(-1500.5, $k->amount);
Assert::same('CZK', $k->currency);
Assert::same('2026-10-06', $k->date->format('Y-m-d'));
Assert::same('Pronajímatel s.r.o. · Nájem říjen', $k->title());
Assert::same(250.0, KbApi::map(['amount' => ['value' => 250, 'currency' => 'CZK'], 'creditDebitIndicator' => 'CREDIT', 'valueDate' => '2026-10-07T10:00:00Z'])->amount);
Assert::null(KbApi::map(['amount' => ['value' => 1]])); // no currency / date

// RB Premium row: creditDebitIndication CRDT, counterparty in relatedParties.counterParty
$r = RbApi::map([
	'entryReference' => 'RB-77',
	'amount' => ['value' => 42000, 'currency' => 'CZK'],
	'creditDebitIndication' => 'CRDT',
	'bookingDate' => '2026-10-08T00:00:00.000+02:00',
	'entryDetails' => ['transactionDetails' => [
		'relatedParties' => ['counterParty' => ['name' => 'Zaměstnavatel a.s.', 'account' => ['accountNumber' => '19-2000145399', 'bankCode' => '0800']]],
		'remittanceInformation' => ['unstructured' => 'Mzda 09/2026'],
		'references' => ['variableSymbol' => '202609'],
	]],
]);
Assert::same('rb-RB-77', $r->id);
Assert::same(42000.0, $r->amount);
Assert::same('2026-10-08', $r->date->format('Y-m-d'));
Assert::same('Zaměstnavatel a.s. · Mzda 09/2026', $r->title());
Assert::same(-10.0, RbApi::map(['amount' => ['value' => '10', 'currency' => 'CZK'], 'creditDebitIndicator' => 'DBIT', 'valueDate' => '2026-10-09'])->amount);

// ČSOB GPC 075 line (fixed widths): code 1 = debit, amount in hellers, date DDMMYY at 92–97, name at 98–117
$gpc = '075' . str_pad('0000001234567890', 16) . str_pad('0000000987654321', 16) . str_pad('0000000004711', 13)
	. str_pad('29900', 12, '0', STR_PAD_LEFT) . '1' . str_pad('0000123456', 10) . '00' . '0300' . '0308'
	. str_pad('0000000000', 10) . '051026' . str_pad('ALBERT CR', 20) . '00000' . '051026';
$o = CsobApi::map($gpc, '012');
Assert::same('csob-4711-012', $o->id);
Assert::same(-299.0, $o->amount);
Assert::same('2026-10-05', $o->date->format('Y-m-d'));
Assert::same('ALBERT CR', $o->counterName);
Assert::count(1, CsobApi::parse("074 header
" . $gpc . "
", '1234567890'));
Assert::count(0, CsobApi::parse($gpc, '999')); // other account → skipped
Assert::null(CsobApi::map(substr_replace($gpc, '9', 60, 1))); // unknown posting code
Assert::same(['https://ceb-bc.csob.cz/f/1.gpc'], CsobApi::fileUrls('<s:Envelope xmlns:s="http://schemas.xmlsoap.org/soap/envelope/"><s:Body><GetDownloadFileListResponse_v2 xmlns="http://ceb-bc.csob.cz/CEBBCWS/GetDownloadFileList_v2"><QueryTimestamp>2026-10-10T07:00:00+02:00</QueryTimestamp><FileList><FileDetail><Type>VYPIS</Type><Url>https://ceb-bc.csob.cz/f/1.gpc</Url></FileDetail><FileDetail><Type>VYPIS</Type></FileDetail><FileDetail><Type>AVIZO</Type><Url>https://x</Url></FileDetail></FileList></GetDownloadFileListResponse_v2></s:Body></s:Envelope>'));

// recurring dates: month end clamps, year adds 12 months
$d = fn(string $s) => new DateTimeImmutable($s);
Assert::same('2026-02-28', RecurringTransaction::addPeriod($d('2026-01-31'), 'month')->format('Y-m-d'));
Assert::same('2026-11-15', RecurringTransaction::addPeriod($d('2026-10-15'), 'month')->format('Y-m-d'));
Assert::same('2027-01-05', RecurringTransaction::addPeriod($d('2026-12-05'), 'month')->format('Y-m-d'));
Assert::same('2027-03-15', RecurringTransaction::addPeriod($d('2026-03-15'), 'year')->format('Y-m-d'));
