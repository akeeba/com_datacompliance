<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest\Tests;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\IntegrationTest\AbstractE2ETestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * What a user actually sees for apostrophes and double quotes written in a Joomla language INI file.
 *
 * THE RULE THIS SETTLES (on every Joomla / PHP pair of the matrix)
 *
 * - An apostrophe is written as a plain `'`.
 * - `''` is NOT an escape: the page shows two apostrophes (`l''utilisateur`).
 * - `\'` is NOT an escape: the page shows the backslash (`l\'utilisateur`).
 * - A double quote is written as `\"`: the page shows `"`.
 * - `"_QQ_"` is dead: the page shows `"_QQ_"` literally.
 *
 * How: a language string that the front-end Options page prints raw with `Text::_()`
 * (COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER, the heading of a user's own page) is given each form in
 * turn, wrapped in markers, and the page is fetched over HTTP as alice. The string is planted two
 * ways, which reach `Joomla\CMS\Language\LanguageHelper::parseIniFile()` through different doors:
 *
 * 1. in the installed component language file, language/en-GB/com_datacompliance.ini — the same kind
 *    of file as the shipped fr-FR one — by appending a redefinition (the last definition wins);
 * 2. as a site language override, language/overrides/en-GB.override.ini.
 *
 * One case uses a value copied verbatim from component/backend/language/fr-FR/com_datacompliance.ini.
 *
 * Joomla 6 caches parsed language files in JPATH_CACHE/language/ (administrator/cache), keyed on the
 * file's mtime, so every write gives the file a new mtime that no earlier write used.
 *
 * Companion to UnitTest/Language/IniQuoteEscapingTest.php, which mirrors core's parsing code in
 * isolation; this test is the evidence that the mirror matches what core really does.
 *
 * @since 4.1.0
 */
class IniQuoteEscapingTest extends AbstractE2ETestCase
{
	/**
	 * The language key the test plants its values in. Printed unescaped on a user's own Options page.
	 *
	 * @since 4.1.0
	 */
	private const KEY = 'COM_DATACOMPLIANCE_OPTIONS_CONSENT_HEADER';

	/**
	 * Markers around the planted value, so it can be cut out of the page exactly.
	 *
	 * @since 4.1.0
	 */
	private const START = 'DCQSTART';

	private const END = 'DCQEND';

	/**
	 * Files this test changed, with their original contents (null: the file did not exist).
	 *
	 * @var   array<string, string|null>
	 * @since 4.1.0
	 */
	private array $originals = [];

	/**
	 * The mtime to give the next write, always later than any used before.
	 *
	 * @var   int
	 * @since 4.1.0
	 */
	private static int $nextMtime = 0;

	/**
	 * Every form of quoting under test.
	 *
	 * @return  array<string, array{0: string, 1: string}>  label => [value as written between the INI quotes, what the user sees]
	 * @since   4.1.0
	 */
	public static function quotingForms(): array
	{
		return [
			"doubled apostrophe '' is NOT an escape: the user sees two apostrophes"      => [
				"l''exportation",
				"l''exportation",
			],
			"backslash-apostrophe \\' is NOT an escape: the user sees the backslash"      => [
				"l\\'exportation",
				"l\\'exportation",
			],
			'a plain apostrophe needs no escaping at all'                                 => [
				"l'exportation",
				"l'exportation",
			],
			'backslash-double-quote \\" is the escape for a double quote'                 => [
				'Cliquez sur \\"Enregistrer\\"',
				'Cliquez sur "Enregistrer"',
			],
			'"_QQ_" is no longer replaced: the user sees it literally'                    => [
				'Cliquez sur "_QQ_"Enregistrer"_QQ_"',
				'Cliquez sur "_QQ_"Enregistrer"_QQ_"',
			],
			// Verbatim from component/backend/language/fr-FR/com_datacompliance.ini,
			// COM_DATACOMPLIANCE_OPTIONS_MANAGE_CONSENT_CURRENTPREFERENCE, as shipped in 4.1.0-dev.
			"the shipped fr-FR string with '' shows the user two apostrophes"             => [
				"La préférence de consentement actuelle de l''utilisateur est :",
				"La préférence de consentement actuelle de l''utilisateur est :",
			],
		];
	}

	/**
	 * Put back every file the test changed.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		foreach ($this->originals as $path => $contents)
		{
			if ($contents === null)
			{
				@unlink($path);

				continue;
			}

			file_put_contents($path, $contents);
			$this->bumpMtime($path);
		}

		$this->originals = [];

		parent::tearDown();
	}

	/**
	 * A value written in the component's own language file, as the user sees it on the page.
	 *
	 * @param   string  $written   The value as written between the INI double quotes.
	 * @param   string  $expected  What the user sees.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('quotingForms')]
	public function testComponentLanguageFile(string $written, string $expected): void
	{
		$path = static::$config->getSiteRoot() . '/language/en-GB/com_datacompliance.ini';

		$this->assertFileExists($path, 'The component\'s front-end language file is not installed where expected.');

		$original = $this->remember($path);

		$this->writeLanguageFile($path, rtrim((string) $original, "\n") . "\n" . $this->line($written));

		$this->assertSame($expected, $this->shownToTheUser());
	}

	/**
	 * A value written in a site language override, as the user sees it on the page.
	 *
	 * @param   string  $written   The value as written between the INI double quotes.
	 * @param   string  $expected  What the user sees.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('quotingForms')]
	public function testLanguageOverride(string $written, string $expected): void
	{
		$path = static::$config->getSiteRoot() . '/language/overrides/en-GB.override.ini';

		$original = $this->remember($path);

		$this->writeLanguageFile($path, ($original === null ? '' : rtrim($original, "\n") . "\n") . $this->line($written));

		$this->assertSame($expected, $this->shownToTheUser());
	}

	/**
	 * The INI line planting a value, between markers, in the test's language key.
	 *
	 * @param   string  $written  The value as written between the INI double quotes.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private function line(string $written): string
	{
		return self::KEY . '="' . self::START . $written . self::END . '"' . "\n";
	}

	/**
	 * Fetch alice's own Options page and cut the planted value out of it.
	 *
	 * The key is printed with a bare `Text::_()`, so what is between the markers is exactly what the
	 * browser displays: no HTML entities are involved.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private function shownToTheUser(): string
	{
		$response = $this->loggedIn('alice')->get($this->siteUrl(['view' => 'options']));

		$this->assertStatus(200, $response);
		$this->assertSame([], $this->newPhpErrors(), 'The page logged PHP errors.');

		$found = preg_match(
			'/' . self::START . '(.*?)' . self::END . '/s', $response->body, $matches
		);

		$this->assertSame(
			1, $found,
			'The planted string is not on the page: the language file was not loaded, or failed to parse.'
		);

		return $matches[1];
	}

	/**
	 * Keep a file's original contents for tearDown().
	 *
	 * @param   string  $path  The file.
	 *
	 * @return  string|null  Its contents; null when it does not exist.
	 * @since   4.1.0
	 */
	private function remember(string $path): ?string
	{
		$contents = is_file($path) ? (string) file_get_contents($path) : null;

		if (!\array_key_exists($path, $this->originals))
		{
			$this->originals[$path] = $contents;
		}

		return $contents;
	}

	/**
	 * Write a language file so that Joomla re-reads it, even with Joomla 6's language cache.
	 *
	 * @param   string  $path      The file.
	 * @param   string  $contents  The new contents.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function writeLanguageFile(string $path, string $contents): void
	{
		$this->assertNotFalse(file_put_contents($path, $contents), "Cannot write $path");

		$this->bumpMtime($path);
	}

	/**
	 * Give a file an mtime no earlier write has used (Joomla 6 keys its language cache on it).
	 *
	 * @param   string  $path  The file.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function bumpMtime(string $path): void
	{
		self::$nextMtime = max(self::$nextMtime + 1, time());

		touch($path, self::$nextMtime);
		clearstatcache(true, $path);
	}
}
