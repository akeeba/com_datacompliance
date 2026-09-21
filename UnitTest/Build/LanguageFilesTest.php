<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Build;

use Akeeba\DataCompliance\UnitTest\LanguageFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The shipped language files.
 *
 * Joomla keeps the LAST definition of a key defined twice, silently. That is how the per-account
 * "not notified, skipping" line of datacompliance:lifecycle:delete came to print "Failed to delete".
 *
 * @since 4.1.0
 */
class LanguageFilesTest extends TestCase
{
	/**
	 * Every shipped .ini file.
	 *
	 * @return  array<string, array{0: string}>
	 * @since   4.1.0
	 */
	public static function languageFiles(): array
	{
		$root  = \dirname(__DIR__, 2);
		$cases = [];

		foreach (['component', 'plugins'] as $dir)
		{
			$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS));

			foreach ($iterator as $file)
			{
				if ($file->getExtension() === 'ini' && !str_contains($file->getPathname(), '/vendor/'))
				{
					$relative         = substr($file->getPathname(), strlen($root) + 1);
					$cases[$relative] = [$file->getPathname()];
				}
			}
		}

		ksort($cases);

		return $cases;
	}

	/**
	 * No key is defined twice.
	 *
	 * @param   string  $path  The .ini file.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('languageFiles')]
	public function testNoDuplicateKeys(string $path): void
	{
		$duplicates = LanguageFile::duplicates($path);

		$this->assertSame([], $duplicates, 'Keys defined more than once; Joomla silently keeps the last one.');
	}

	/**
	 * Every line is a comment, blank, a section header or a KEY="value" pair Joomla can parse.
	 *
	 * @param   string  $path  The .ini file.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('languageFiles')]
	public function testEveryLineParses(string $path): void
	{
		$bad = [];

		foreach (file($path, FILE_IGNORE_NEW_LINES) as $number => $line)
		{
			if (trim($line) === '' || preg_match('/^\s*;/', $line) || preg_match('/^\s*\[[^\]]+\]\s*$/', $line))
			{
				continue;
			}

			if (!preg_match('/^\s*[A-Z0-9_\-.]+\s*=\s*"(?:[^"\\\\]|\\\\.)*"\s*$/', $line))
			{
				$bad[] = sprintf('line %d: %s', $number + 1, mb_substr($line, 0, 100));
			}
		}

		$this->assertSame([], $bad, 'Lines Joomla cannot parse.');
	}
}
