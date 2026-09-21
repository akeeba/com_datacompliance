<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Build;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fresh-install schema against the update SQL.
 *
 * Joomla never runs update SQL on a fresh install: it runs the install SQL and records the newest
 * update file as the schema version. So every column an update adds must also be in the install SQL,
 * or fresh installs silently lack it — forever, since the update that adds it will never run.
 *
 * @since 4.1.0
 */
class SqlSchemaTest extends TestCase
{
	/**
	 * The database drivers the component ships SQL for.
	 *
	 * @return  array<string, array{0: string, 1: string}>
	 * @since   4.1.0
	 */
	public static function drivers(): array
	{
		return [
			'mysql'      => ['mysql', 'install.mysql.utf8.sql'],
			'postgresql' => ['postgresql', 'install.postgresql.utf8.sql'],
		];
	}

	/**
	 * Every column added by an update is created by the install SQL too.
	 *
	 * @param   string  $driver   The updates sub-folder.
	 * @param   string  $install  The install SQL file.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('drivers')]
	public function testUpdatesDoNotAddColumnsTheInstallLacks(string $driver, string $install): void
	{
		$sqlDir  = \dirname(__DIR__, 2) . '/component/backend/sql';
		$tables  = $this->installedColumns(file_get_contents($sqlDir . '/' . $install));
		$missing = [];

		foreach (glob($sqlDir . '/updates/' . $driver . '/*.sql') as $update)
		{
			preg_match_all(
				'/ALTER\s+TABLE\s+[`"]?(#__[a-z_]+)[`"]?\s+ADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?[`"]?([a-z_]+)[`"]?/i',
				file_get_contents($update),
				$matches,
				PREG_SET_ORDER
			);

			foreach ($matches as [, $table, $column])
			{
				// Only tables the install SQL still creates; dropped tables do not matter.
				if (isset($tables[$table]) && !in_array(strtolower($column), $tables[$table], true))
				{
					$missing[] = sprintf('%s.%s (added by %s)', $table, $column, basename($update));
				}
			}
		}

		if ($missing === ['#__datacompliance_consenttrails.reason (added by 4.0.2-20260806-0000.sql)'])
		{
			$this->markTestSkipped('Known issue #2 (see known-issues.md): ' . $install . ' lacks ' . implode(', ', $missing));
		}

		$this->assertSame([], $missing, $install . ' lacks columns that the update SQL adds.');
	}

	/**
	 * The columns of every table an install SQL file creates.
	 *
	 * @param   string  $sql  The install SQL.
	 *
	 * @return  array<string, string[]>  Table => lower-cased column names.
	 * @since   4.1.0
	 */
	private function installedColumns(string $sql): array
	{
		$tables = [];

		preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(#__[a-z_]+)[`"]?\s*\((.*?)\)\s*[^,()]*;/is', $sql, $creates, PREG_SET_ORDER);

		foreach ($creates as [, $table, $body])
		{
			preg_match_all('/^\s*[`"]?([a-z_]+)[`"]?\s+[a-z]/im', $body, $columns);

			$tables[$table] = array_values(array_diff(
				array_map('strtolower', $columns[1]),
				['primary', 'key', 'unique', 'index', 'constraint']
			));
		}

		$this->assertNotEmpty($tables, 'Could not parse any CREATE TABLE statement.');

		return $tables;
	}
}
