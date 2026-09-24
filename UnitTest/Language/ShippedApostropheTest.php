<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Language;

use PHPUnit\Framework\TestCase;

/**
 * No shipped language string writes an apostrophe as `''` or `\'`.
 *
 * Neither is an escape: Joomla shows `''` as two apostrophes and `\'` with its backslash (see
 * IniQuoteEscapingTest in this folder for the proof). An apostrophe is a plain `'`.
 *
 * The files scanned are every .ini the release packages carry, following build.xml and the Akeeba
 * Build Tools' common.xml: the component package (`component/`, zipped whole), every module and
 * plugin package (`modules/<client>/<name>`, `plugins/<folder>/<name>`) and the package-level
 * language files copied from `build/templates/language/`. Bundled Composer libraries are not ours.
 *
 * @since 4.1.0
 */
class ShippedApostropheTest extends TestCase
{
	/**
	 * Every shipped .ini file, relative to the repository root.
	 *
	 * @return  string[]
	 * @since   4.1.0
	 */
	private static function shippedLanguageFiles(): array
	{
		$root  = \dirname(__DIR__, 2);
		$files = [];

		foreach (['component', 'modules', 'plugins', 'build/templates/language'] as $dir)
		{
			if (!is_dir($root . '/' . $dir))
			{
				continue;
			}

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
			);

			foreach ($iterator as $file)
			{
				if ($file->getExtension() === 'ini' && !str_contains($file->getPathname(), '/vendor/'))
				{
					$files[] = substr($file->getPathname(), strlen($root) + 1);
				}
			}
		}

		sort($files);

		return $files;
	}

	/**
	 * The scan actually reaches the component, the plugins and the package-level language files.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testScansEveryPackagePart(): void
	{
		$files = self::shippedLanguageFiles();

		foreach (
			[
				'component/backend/language/en-GB/com_datacompliance.ini',
				'component/frontend/language/en-GB/com_datacompliance.ini',
				'plugins/system/datacompliance/language/en-GB/plg_system_datacompliance.ini',
				'build/templates/language/en-GB/pkg_datacompliance.sys.ini',
			] as $expected
		)
		{
			$this->assertContains($expected, $files, 'The shipped language file scan misses a package part.');
		}
	}

	/**
	 * No value contains `''` or `\'`.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testNoEscapedApostrophes(): void
	{
		$root = \dirname(__DIR__, 2);
		$bad  = [];

		foreach (self::shippedLanguageFiles() as $relative)
		{
			foreach (file($root . '/' . $relative, FILE_IGNORE_NEW_LINES) as $number => $line)
			{
				if (!preg_match('/^\s*([A-Z0-9_\-.]+)\s*=\s*"(.*)"\s*$/', $line, $match))
				{
					continue;
				}

				if (str_contains($match[2], "''") || str_contains($match[2], "\\'"))
				{
					$bad[] = sprintf('%s:%d:%s', $relative, $number + 1, $match[1]);
				}
			}
		}

		$this->assertSame(
			[],
			$bad,
			sprintf(
				"%d language string(s) write an apostrophe as '' or \\' — Joomla shows both literally. Use a plain '.",
				\count($bad)
			)
		);
	}
}
