<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncStudentDormitoriesFromMiddata extends Command
{
	protected $signature = 'sync:student-dormitories-from-middata';

	protected $description = '同步中间库新生/老生宿舍信息到本地 student_dormitories';

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
		$sourceTables = [
			// Higher-priority sources should appear earlier. Later sources will not overwrite them.
			't_ejxyybt_bzksxsssxx',
			't_ejxyybt_bzkslsssxx',
		];
		$seenStudentNumbers = [];

		foreach ($sourceTables as $sourceTable) {
			$mapping = $this->resolveSourceColumns($sourceTable);

			DB::connection('middata')
				->table($sourceTable)
				->select($this->buildSelectColumns($mapping))
				->whereNotNull($mapping['xh'])
				->where($mapping['xh'], '!=', '')
				->orderBy($mapping['xh'])
				->chunk($chunkSize, function ($rows) use (&$totalRead, &$totalUpserted, &$totalSkippedBlankXh, &$totalSkippedDuplicateXh, &$seenStudentNumbers, &$lastLogged, $progressStep, $sourceTable) {
					$totalRead += $rows->count();
					$now = now();

					$payload = [];

					foreach ($rows as $row) {
						$studentNumber = trim((string) ($row->xh ?? ''));

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
							'ssh' => $this->nullableString($row->ssh ?? null),
							'ch' => $this->nullableString($row->ch ?? null),
							'xz' => $this->nullableString($row->xz ?? null),
							'qslx' => $this->nullableString($row->qslx ?? null),
							'xb' => $this->nullableString($row->xb ?? null),
							'source_table' => $sourceTable,
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
						$this->info("已同步: {$totalUpserted} 条...");
					}
				});
		}

		$elapsed = round(microtime(true) - $startedAt, 2);
		$this->info("宿舍信息同步完成，读取 {$totalRead} 条，写入 {$totalUpserted} 条，跳过空学号 {$totalSkippedBlankXh} 条，跳过重复学号 {$totalSkippedDuplicateXh} 条，耗时 {$elapsed} 秒");

		return self::SUCCESS;
	}

	private function resolveSourceColumns(string $sourceTable): array
	{
		$columns = collect(DB::connection('middata')->getSchemaBuilder()->getColumnListing($sourceTable));

		$mapping = [
			'xh' => $this->firstExistingColumn($columns, ['xh', 'xgh', 'stu_no', 'id']),
			'xm' => $this->firstExistingColumn($columns, ['xm', 'name']),
			'xy' => $this->firstExistingColumn($columns, ['xy', 'dwmc']),
			'zy' => $this->firstExistingColumn($columns, ['zy']),
			'bj' => $this->firstExistingColumn($columns, ['bj', 'bjmc']),
			'nj' => $this->firstExistingColumn($columns, ['nj']),
			'ssh' => $this->firstExistingColumn($columns, ['ssh']),
			'ch' => $this->firstExistingColumn($columns, ['ch']),
			'xz' => $this->firstExistingColumn($columns, ['xz']),
			'qslx' => $this->firstExistingColumn($columns, ['qslx', 'qsls']),
			'xb' => $this->firstExistingColumn($columns, ['xb', 'xbm']),
		];

		if ($mapping['xh'] === null) {
			throw new \RuntimeException("中间库表 {$sourceTable} 未找到学号字段，支持字段: xh/xgh/stu_no/id");
		}

		return $mapping;
	}

	private function firstExistingColumn($columns, array $candidates): ?string
	{
		foreach ($candidates as $candidate) {
			if ($columns->contains($candidate)) {
				return $candidate;
			}
		}

		return null;
	}

	private function buildSelectColumns(array $mapping): array
	{
		return collect(['xh', 'xm', 'xy', 'zy', 'bj', 'nj', 'ssh', 'ch', 'xz', 'qslx', 'xb'])
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
}

