<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Language;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How apostrophes and double quotes in a Joomla language INI value reach the user.
 *
 * THE RULE THIS SETTLES
 *
 * - An apostrophe is written as a plain `'`. Nothing else.
 * - `''` is NOT an escape. Joomla shows it as two apostrophes (`l''exportation`).
 * - `\'` is NOT an escape. Joomla shows the backslash (`l\'exportation`).
 * - A double quote is written as `\"`; Joomla turns it into `"`.
 * - `"_QQ_"` is dead. Joomla 4+ no longer replaces it, so the user sees `"_QQ_"`.
 *
 * WHAT JOOMLA ACTUALLY RUNS
 *
 * The CMS does not read language files with the Framework's `Joomla\Language\Parser\IniParser` (that
 * class is only used by the language debugger). `Joomla\CMS\Language\Language::parse()` — used for
 * extension language files and for the language overrides alike — calls
 * `Joomla\CMS\Language\LanguageHelper::parseIniFile()`, which does:
 *
 *     $strings = parse_ini_file($fileName, false, INI_SCANNER_RAW);
 *     // or, when parse_ini_file() is disabled:
 *     $strings = parse_ini_string(file_get_contents($fileName), false, INI_SCANNER_RAW);
 *     $strings = str_replace('\"', '"', $strings);
 *
 * i.e. PHP's RAW scanner, not the default NORMAL one, followed by one post-processing step. There is
 * no `"_QQ_"` replacement anywhere. When a string is output, `Language::_()` (behind `Text::_()` and
 * `Text::sprintf()`) interprets only `\\`, `\t` and `\n`. Both pieces of code were checked to be
 * identical in Joomla 5.4.8, 6.0.4 and 6.1.3 (6.x only adds a PHP-file cache of the parsed array,
 * which does not change the values).
 *
 * This suite cannot boot Joomla, so both steps are mirrored verbatim below and exercised through both
 * branches of parseIniFile(). The E2E counterpart, tests/integration/src/Tests/IniQuoteEscapingTest.php,
 * renders the same values through a real installed Joomla on a real page, on every supported
 * Joomla / PHP pair.
 *
 * @since 4.1.0
 */
class IniQuoteEscapingTest extends TestCase
{
	/**
	 * Every form of quoting under test.
	 *
	 * @return  array<string, array{0: string, 1: string}>  label => [value as written between the INI quotes, what the user sees]
	 * @since   4.1.0
	 */
	public static function quotingForms(): array
	{
		return [
			"doubled apostrophe '' is NOT an escape: the user sees two apostrophes" => [
				"l''exportation",
				"l''exportation",
			],
			"backslash-apostrophe \\' is NOT an escape: the user sees the backslash" => [
				"l\\'exportation",
				"l\\'exportation",
			],
			"a plain apostrophe needs no escaping at all"                            => [
				"l'exportation",
				"l'exportation",
			],
			'backslash-double-quote \\" is the escape for a double quote'            => [
				'Cliquez sur \\"Enregistrer\\"',
				'Cliquez sur "Enregistrer"',
			],
			'"_QQ_" is no longer replaced: the user sees it literally'               => [
				'Cliquez sur "_QQ_"Enregistrer"_QQ_"',
				'Cliquez sur "_QQ_"Enregistrer"_QQ_"',
			],
			// Not a recommendation: the RAW scanner happens to keep a bare quote, but Joomla's language
			// debugger (Language::debugFile()) reports the line as an error. Write \" instead.
			'a bare double quote survives the RAW scanner (but debugFile() flags it)' => [
				'Cliquez sur "Enregistrer"',
				'Cliquez sur "Enregistrer"',
			],
		];
	}

	/**
	 * What a value comes out as once Joomla has loaded the file.
	 *
	 * @param   string  $written   The value as written between the INI double quotes.
	 * @param   string  $expected  What Joomla returns.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('quotingForms')]
	public function testJoomlaLoadsTheValueAs(string $written, string $expected): void
	{
		$ini = 'EXAMPLE="' . $written . '"' . "\n";

		$this->assertSame($expected, self::parseIniFile($ini, true)['EXAMPLE'] ?? null, 'parse_ini_file() branch');
		$this->assertSame($expected, self::parseIniFile($ini, false)['EXAMPLE'] ?? null, 'parse_ini_string() branch');
	}

	/**
	 * What the user sees once `Text::_()` has output the value (its backslash interpretation included).
	 *
	 * @param   string  $written   The value as written between the INI double quotes.
	 * @param   string  $expected  What the user sees.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('quotingForms')]
	public function testTextApiShowsTheUser(string $written, string $expected): void
	{
		$loaded = self::parseIniFile('EXAMPLE="' . $written . '"' . "\n", true)['EXAMPLE'] ?? '';

		$this->assertSame($expected, self::translate($loaded));
	}

	/**
	 * Verbatim mirror of the parsing part of `Joomla\CMS\Language\LanguageHelper::parseIniFile()`.
	 *
	 * @param   string  $contents     The INI file's contents.
	 * @param   bool    $viaFile      True: the parse_ini_file() branch; false: the parse_ini_string() one.
	 *
	 * @return  array<string, string>
	 * @since   4.1.0
	 */
	private static function parseIniFile(string $contents, bool $viaFile): array
	{
		$fileName = tempnam(sys_get_temp_dir(), 'dcini');

		try
		{
			file_put_contents($fileName, $contents);

			if (!$viaFile)
			{
				$contents = file_get_contents($fileName);
				$strings  = parse_ini_string($contents, false, INI_SCANNER_RAW);
			}
			else
			{
				$strings = parse_ini_file($fileName, false, INI_SCANNER_RAW);
			}
		}
		finally
		{
			@unlink($fileName);
		}

		// Ini files are processed in the "RAW" mode of parse_ini_string, leaving escaped quotes untouched - lets postprocess them
		$strings = str_replace('\"', '"', $strings);

		return \is_array($strings) ? $strings : [];
	}

	/**
	 * Verbatim mirror of the output part of `Joomla\CMS\Language\Language::_()` with its defaults
	 * (`$jsSafe = false`, `$interpretBackSlashes = true`), which is what `Text::_()` uses.
	 *
	 * @param   string  $string  The loaded language string.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private static function translate(string $string): string
	{
		if (str_contains($string, '\\'))
		{
			// Interpret \n and \t characters
			$string = str_replace(['\\\\', '\t', '\n'], ["\\", "\t", "\n"], $string);
		}

		return $string;
	}
}
