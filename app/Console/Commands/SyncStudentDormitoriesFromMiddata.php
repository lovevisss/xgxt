<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentDormitoriesFromMiddata extends Command
{
	protected $signature = 'sync:student-dormitories-from-middata';

	protected $description = 'Sync student dormitory bed information from middata to local student_dormitories';

	private const SOURCE_TABLE = 't_ejxyybt_ssxxb';

	private const OLD_SOURCE_TABLES = [
		't_ejxyybt_bzksxsssxx',
		't_ejxyybt_bzkslsssxx',
	];

	public function handle(): int
	{
		$startedAt = microtime(true);
		$totalRead = 0;
		$totalUpserted = 0;
		$totalSkippedBlankXh = 0;
		$totalSkippedDuplicateXh = 0;
		$chunkSize = 2000;
		$progressStep = 5000;
		$lastLogged = 0;
		$seenStudentNumbers = [];
		$mapping = $this->resolveSourceColumns(self::SOURCE_TABLE);

		$removedOldRows = DB::table('student_dormitories')
			->whereIn('source_table', self::OLD_SOURCE_TABLES)
			->delete();

		DB::connection('middata')
			->table(self::SOURCE_TABLE)
			->select($this->buildSelectColumns($mapping))
			->where($mapping['type'], 7)
			->whereNotNull($mapping['xh'])
			->where($mapping['xh'], '!=', '')
			->orderBy($mapping['xh'])
			->chunk($chunkSize, function ($rows) use (&$totalRead, &$totalUpserted, &$totalSkippedBlankXh, &$totalSkippedDuplicateXh, &$seenStudentNumbers, &$lastLogged, $progressStep) {
				$totalRead += $rows->count();
				$now = now();
				$payload = [];

				foreach ($rows as $row) {
					$studentNumber = trim((string) ($row->xh ?? ''));
					[$roomNumber, $bedNumber] = $this->parseBedRemark($row->bz ?? null);

					if ($studentNumber === '') {
						$totalSkippedBlankXh++;
						continue;
					}

					if (isset($seenStudentNumbers[$studentNumber])) {
						$totalSkippedDuplicateXh++;
						continue;
					}

					$payload[] = [
						'xh' => $studentNumber,
						'xm' => $this->nullableString($row->xm ?? null),
						'xy' => $this->nullableString($row->xy ?? null),
						'zy' => $this->nullableString($row->zy ?? null),
						'bj' => $this->nullableString($row->bj ?? null),
						'nj' => $this->nullableString($row->nj ?? null),
						'ssh' => $roomNumber ?? $this->nullableString($row->ssh ?? null),
						'ch' => $bedNumber ?? $this->nullableString($row->ch ?? null),
						'xz' => $this->nullableString($row->xz ?? null),
						'qslx' => $this->nullableString($row->qslx ?? null),
						'xb' => $this->nullableString($row->xb ?? null),
						'source_table' => self::SOURCE_TABLE,
						'synced_at' => $now,
						'updated_at' => $now,
						'created_at' => $now,
					];

					$seenStudentNumbers[$studentNumber] = true;
				}

				if ($payload === []) {
					return;
				}

				DB::table('student_dormitories')->upsert(
					$payload,
					['xh'],
					['xm', 'xy', 'zy', 'bj', 'nj', 'ssh', 'ch', 'xz', 'qslx', 'xb', 'source_table', 'synced_at', 'updated_at']
				);

				$totalUpserted += count($payload);

				if ($totalUpserted - $lastLogged >= $progressStep) {
					$lastLogged = $totalUpserted;
					$this->info("Synced {$totalUpserted} dormitory rows...");
				}
			});

		$removedStaleRows = DB::table('student_dormitories')
			->when($seenStudentNumbers !== [], fn ($query) => $query->whereNotIn('xh', array_keys($seenStudentNumbers)))
			->delete();

		$elapsed = round(microtime(true) - $startedAt, 2);
		$this->info("Dormitory sync completed. Read {$totalRead}, upserted {$totalUpserted}, skipped blank student numbers {$totalSkippedBlankXh}, skipped duplicate student numbers {$totalSkippedDuplicateXh}, removed old-source rows {$removedOldRows}, removed stale new-source rows {$removedStaleRows}, elapsed {$elapsed}s.");

		return self::SUCCESS;
	}

	private function resolveSourceColumns(string $sourceTable): array
	{
		$columns = collect(DB::connection('middata')->getSchemaBuilder()->getColumnListing($sourceTable));

		$mapping = [
			'type' => $this->firstExistingColumn($columns, ['type', 'sslx']),
			'xh' => $this->firstExistingColumn($columns, ['user_no', 'xgh']),
			'xm' => $this->firstExistingColumn($columns, ['xm', 'user_name', 'name']),
			'xy' => $this->firstExistingColumn($columns, ['xy', 'dwmc', 'college_name', 'dept_name']),
			'zy' => $this->firstExistingColumn($columns, ['zy', 'major_name']),
			'bj' => $this->firstExistingColumn($columns, ['bj', 'bjmc', 'class_name']),
			'nj' => $this->firstExistingColumn($columns, ['nj', 'grade']),
			'ssh' => $this->firstExistingColumn($columns, ['ssh', 'ssmc', 'room_no', 'room_name', 'dorm_no', 'dorm_room_no']),
			'ch' => $this->firstExistingColumn($columns, ['ch', 'cwbq', 'bed_no', 'bed_name']),
			'bz' => $this->firstExistingColumn($columns, ['bz']),
			'xz' => $this->firstExistingColumn($columns, ['xz', 'schooling_length']),
			'qslx' => $this->firstExistingColumn($columns, ['qslx', 'qsls', 'fjlx', 'room_type', 'dorm_type']),
			'xb' => $this->firstExistingColumn($columns, ['xb', 'xbm', 'gender']),
		];

		if ($mapping['type'] === null) {
			throw new \RuntimeException("Middata table {$sourceTable} does not have required type column. Available columns: ".$columns->implode(', '));
		}

		if ($mapping['xh'] === null) {
			throw new \RuntimeException("Middata table {$sourceTable} does not have required student number column user_no. Available columns: ".$columns->implode(', '));
		}

		return $mapping;
	}

	private function firstExistingColumn($columns, array $candidates): ?string
	{
		$columnsByLowerName = $columns->mapWithKeys(fn (string $column) => [strtolower(trim($column)) => $column]);

		foreach ($candidates as $candidate) {
			$column = $columnsByLowerName->get(strtolower(trim($candidate)));

			if ($column !== null) {
				return $column;
			}
		}

		return null;
	}

	private function buildSelectColumns(array $mapping): array
	{
		return collect(['xh', 'xm', 'xy', 'zy', 'bj', 'nj', 'ssh', 'ch', 'bz', 'xz', 'qslx', 'xb'])
			->map(function (string $alias) use ($mapping) {
				$source = $mapping[$alias] ?? null;

				return $source !== null
					? DB::raw("`{$source}` as `{$alias}`")
					: DB::raw("NULL as `{$alias}`");
			})
			->all();
	}

	private function nullableString(mixed $value): ?string
	{
		$normalized = trim((string) $value);

		return $normalized === '' ? null : $normalized;
	}

	private function parseBedRemark(mixed $value): array
	{
		$remark = $this->nullableString($value);

		if ($remark === null) {
			return [null, null];
		}

		$parts = array_values(array_filter(
			array_map(fn (string $part) => trim($part), explode('#', $remark)),
			fn (string $part) => $part !== ''
		));

		if (count($parts) < 3) {
			return [null, null];
		}

		return [
			$parts[0].'#'.$parts[1],
			$parts[2],
		];
	}
}
