<?php declare(strict_types=1);

namespace App\Model\Bank;

use DateTimeImmutable;


/**
 * ČSOB — CEB Business Connector (SOAP over mTLS + statement files; ported from Bronze OpenBanking).
 * ČSOB has no REST API for movements: the user activates Business Connector in CEB, gets a contract
 * number (stored in clientId) and a client certificate, and turns on the GPC/ABO statement format (BBGPC).
 * We ask GetDownloadFileList_v2 for new statements (type VYPIS), download them and parse GPC.
 * Statements are generated after the day closes → movements show up D+1. PrevQueryTimestamp
 * max. 45 days back, 30 calls per 20 minutes per certificate.
 * @see https://www.csob.cz/firmy/prehled-on-line-kanalu-a-aplikaci/business-connector
 */
final class CsobApi
{
	/** [production, sandbox] */
	private const Api = ['https://ceb-bc.csob.cz/cebbc/api', 'https://testceb-bc.csob.cz/cebbc/api'];
	private const Namespace = 'http://ceb-bc.csob.cz/CEBBCWS/GetDownloadFileList_v2';
	private const MaxDays = 44;


	/**
	 * ponytail: no bank cursor (QueryTimestamp) stored — we ask from `$since`, re-downloaded statements are deduped.
	 * @param array{string, ?string} $cert [path, password]
	 * @return list<BankMovement>
	 */
	public function movements(BankAccount $account, DateTimeImmutable $since, array $cert): array
	{
		$contract = trim((string) $account->clientId);
		if (!ctype_digit($contract)) {
			throw new BankException('Chybí číslo smlouvy CEB (jen číslice).');
		}
		$from = htmlspecialchars(max($since, new DateTimeImmutable('-' . self::MaxDays . ' days'))->format('Y-m-d\TH:i:sP'), ENT_XML1);
		$xml = Http::request('POST', self::Api[(int) $account->sandbox], [
			'Content-Type' => 'text/xml; charset=utf-8',
			'SOAPAction' => '"GetDownloadFileList_v2"',
		], '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"><soap:Body>'
			. '<GetDownloadFileListRequest_v2 xmlns="' . self::Namespace . '">'
			. "<ContractNumber>$contract</ContractNumber><PrevQueryTimestamp>$from</PrevQueryTimestamp>"
			. "<Filter><FileTypes><FileType>VYPIS</FileType></FileTypes><CreatedAfter>$from</CreatedAfter></Filter>"
			. '</GetDownloadFileListRequest_v2></soap:Body></soap:Envelope>', $cert);

		$result = [];
		foreach (self::fileUrls($xml) as $url) {
			array_push($result, ...self::parse(Http::request('GET', $url, cert: $cert), $account->accountRef));
		}
		return $result;
	}


	/** @return list<string> statement URLs from GetDownloadFileListResponse_v2 (files still being prepared have no URL yet) */
	public static function fileUrls(string $xml): array
	{
		$doc = new \DOMDocument;
		if (!@$doc->loadXML($xml)) {
			throw new BankException('ČSOB vrátila neplatné XML.');
		}
		$xpath = new \DOMXPath($doc);
		$xpath->registerNamespace('m', self::Namespace);
		$urls = [];
		foreach ($xpath->query('//m:FileDetail') ?: [] as $detail) {
			$type = strtoupper(trim((string) $xpath->evaluate('string(m:Type)', $detail)));
			$url = trim((string) $xpath->evaluate('string(m:Url)', $detail));
			if (($type === '' || $type === 'VYPIS') && $url !== '') {
				$urls[] = $url;
			}
		}
		return $urls;
	}


	/**
	 * GPC/ABO statement: line 074 = statement header, 075 = item. Items of other accounts are skipped when $onlyAccount is set.
	 * @return list<BankMovement>
	 */
	public static function parse(string $content, ?string $onlyAccount = null): array
	{
		$only = ltrim(preg_replace('~\D~', '', (string) $onlyAccount), '0');
		$statement = '';
		$result = [];
		foreach (preg_split('~\r\n|\n|\r~', $content) ?: [] as $line) {
			if (str_starts_with($line, '074')) {
				$statement = trim(substr($line, 105, 3));
			} elseif (($only === '' || ltrim(trim(substr($line, 3, 16)), '0') === $only) && ($m = self::map($line, $statement))) {
				$result[] = $m;
			}
		}
		return $result;
	}


	/**
	 * One GPC 075 line (1-based): 4–19 account, 20–35 counter account, 36–48 document no., 49–60 amount in hellers,
	 * 61 code (1 debit, 2 credit, 4 debit reversal, 5 credit reversal), 62–71 VS, 92–97 value date DDMMYY, 98–117 counter name.
	 * GPC has no currency — ČSOB statements are taken as CZK.
	 */
	public static function map(string $line, string $statement = ''): ?BankMovement
	{
		$sign = match (substr($line, 60, 1)) {
			'2', '4' => 1,
			'1', '5' => -1,
			default => null,
		};
		if (!str_starts_with($line, '075') || strlen($line) < 97 || $sign === null || !preg_match('~^(\d\d)(\d\d)(\d\d)$~', substr($line, 91, 6), $d)) {
			return null;
		}
		$document = ltrim(trim(substr($line, 35, 13)), '0');
		$name = trim(substr($line, 97, 20));
		$name = mb_check_encoding($name, 'UTF-8') ? $name : (string) iconv('windows-1250', 'UTF-8//IGNORE', $name); // GPC is usually cp1250

		return new BankMovement(
			id: 'csob-' . ($document !== '' ? $document : sha1($line)) . ($statement !== '' ? '-' . $statement : ''),
			date: new DateTimeImmutable("20$d[3]-$d[2]-$d[1]"),
			amount: $sign * ((int) substr($line, 48, 12)) / 100,
			currency: 'CZK',
			counterName: $name !== '' ? $name : null,
			message: null,
		);
	}
}
