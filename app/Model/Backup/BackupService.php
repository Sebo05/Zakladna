<?php declare(strict_types=1);

namespace App\Model\Backup;

use Nextras\Dbal\Connection;


/**
 * Plain-PHP SQL dump (no mysqldump needed on hosting) → backup/db-YYYY-MM-DD-His.sql.gz.
 * Keeps the newest `Keep` files. Restore: gunzip -c file.sql.gz | mysql dbname
 * ponytail: whole tables are read row by row into one gzip stream; fine for a hobby DB, use mysqldump past ~100 MB.
 */
final class BackupService
{
	private const Keep = 14;
	private const RowsPerInsert = 200;


	public function __construct(
		private readonly Connection $db,
		private readonly string $dir = __DIR__ . '/../../../backup',
	) {
	}


	/** @return string path of the created dump */
	public function run(): string
	{
		if (!is_dir($this->dir)) {
			mkdir($this->dir, 0700, true);
		}
		$file = $this->dir . '/db-' . date('Y-m-d-His') . '.sql.gz';
		$gz = gzopen($file, 'wb6') ?: throw new \RuntimeException("Cannot write $file");
		$this->db->connect();
		$link = $this->db->getDriver()->getResourceHandle();

		gzwrite($gz, "-- Základna backup " . date('c') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n");
		foreach ($this->db->query('SHOW FULL TABLES WHERE Table_type = %s', 'BASE TABLE')->fetchAll() as $row) {
			$table = array_values($row->toArray())[0];
			$create = $this->db->query('SHOW CREATE TABLE %table', $table)->fetch()?->toArray();
			gzwrite($gz, "DROP TABLE IF EXISTS `$table`;\n" . end($create) . ";\n\n");

			$batch = [];
			foreach ($this->db->query('SELECT * FROM %table', $table) as $row) {
				$batch[] = '(' . implode(',', array_map(fn($v) => $this->quote($link, $v), $row->toArray())) . ')';
				if (count($batch) >= self::RowsPerInsert) {
					gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
					$batch = [];
				}
			}
			if ($batch) {
				gzwrite($gz, "INSERT INTO `$table` VALUES\n" . implode(",\n", $batch) . ";\n");
			}
			gzwrite($gz, "\n");
		}
		gzwrite($gz, "SET FOREIGN_KEY_CHECKS = 1;\n");
		gzclose($gz);

		$this->prune();
		return realpath($file) ?: $file;
	}


	private function quote(\mysqli $link, mixed $v): string
	{
		return match (true) {
			$v === null => 'NULL',
			is_bool($v) => $v ? '1' : '0',
			is_int($v), is_float($v) => (string) $v,
			$v instanceof \DateTimeInterface => "'" . $v->format('Y-m-d H:i:s') . "'",
			default => "'" . $link->real_escape_string((string) $v) . "'",
		};
	}


	private function prune(): void
	{
		$files = glob($this->dir . '/db-*.sql.gz') ?: [];
		rsort($files); // names sort by date
		foreach (array_slice($files, self::Keep) as $old) {
			unlink($old);
		}
	}
}
