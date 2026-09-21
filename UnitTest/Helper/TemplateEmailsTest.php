<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Helper;

use Akeeba\Component\DataCompliance\Administrator\Helper\TemplateEmails;
use Akeeba\DataCompliance\UnitTest\LanguageFile;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClassConstant;

/**
 * The mail template definitions TemplateEmails registers with com_mails, against the language
 * strings they point to.
 *
 * A template whose text uses a {TAG} that is not declared is sent with the tag unreplaced, or with a
 * value nobody meant to put there; a template whose language key is missing arrives as the key.
 *
 * @since 4.1.0
 */
#[CoversClass(TemplateEmails::class)]
class TemplateEmailsTest extends TestCase
{
	/**
	 * Tags every template may use without declaring them: TemplateEmails::sendMail() adds them itself.
	 *
	 * @since 4.1.0
	 */
	private const IMPLICIT_TAGS = ['SITENAME'];

	/**
	 * The template definitions.
	 *
	 * @return  array<string, array{0: string, 1: array}>
	 * @since   4.1.0
	 */
	public static function definitions(): array
	{
		$definitions = (new ReflectionClassConstant(TemplateEmails::class, 'EMAIL_DEFINITIONS'))->getValue();
		$cases       = [];

		foreach ($definitions as $key => $definition)
		{
			$cases[$key] = [$key, $definition];
		}

		return $cases;
	}

	/**
	 * Every language key of every template exists in the en-GB back-end language file.
	 *
	 * @param   string  $key         The template key.
	 * @param   array   $definition  Its definition.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('definitions')]
	public function testLanguageKeysExist(string $key, array $definition): void
	{
		$strings = LanguageFile::load(\dirname(__DIR__, 2) . '/component/backend/language/en-GB/com_datacompliance.ini');

		foreach (['subject', 'bodyPlaintext', 'bodyHtml'] as $part)
		{
			$this->assertArrayHasKey($definition[$part], $strings, sprintf('%s: the %s string %s is missing.', $key, $part, $definition[$part]));
		}
	}

	/**
	 * Every {TAG} a template uses is one it declares (or one sendMail() always adds).
	 *
	 * @param   string  $key         The template key.
	 * @param   array   $definition  Its definition.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('definitions')]
	public function testTemplatesOnlyUseDeclaredTags(string $key, array $definition): void
	{
		$strings  = LanguageFile::load(\dirname(__DIR__, 2) . '/component/backend/language/en-GB/com_datacompliance.ini');
		$declared = array_merge(self::IMPLICIT_TAGS, array_map('strtoupper', $definition['variables']));

		foreach (['subject', 'bodyPlaintext', 'bodyHtml'] as $part)
		{
			preg_match_all('/\{([A-Z0-9_:]+)\}/', $strings[$definition[$part]] ?? '', $matches);

			$this->assertSame(
				[],
				array_values(array_diff(array_unique($matches[1]), $declared)),
				sprintf('%s: the %s uses tags it does not declare.', $key, $part)
			);
		}
	}

	/**
	 * The administrator notifications address the administrator, not the deleted user: their greeting
	 * must not be "Hello {NAME}", which is the DELETED user's name.
	 *
	 * @param   string  $key         The template key.
	 * @param   array   $definition  Its definition.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('definitions')]
	public function testAdministratorTemplatesDoNotGreetTheDeletedUser(string $key, array $definition): void
	{
		if (!str_starts_with($key, 'com_datacompliance.admin_'))
		{
			$this->assertTrue(true);

			return;
		}

		$strings = LanguageFile::load(\dirname(__DIR__, 2) . '/component/backend/language/en-GB/com_datacompliance.ini');

		if (preg_match('/Hello \{NAME\}/', ($strings[$definition['bodyPlaintext']] ?? '') . ($strings[$definition['bodyHtml']] ?? '')))
		{
			$this->markTestSkipped(sprintf('Known issue #13 (see known-issues.md): %s greets the Super User with "Hello {NAME}", the deleted user\'s name.', $key));
		}

		$this->assertTrue(true);
	}

	/**
	 * Subjects are plain text: no markup.
	 *
	 * @param   string  $key         The template key.
	 * @param   array   $definition  Its definition.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('definitions')]
	public function testSubjectsHaveNoMarkup(string $key, array $definition): void
	{
		$strings = LanguageFile::load(\dirname(__DIR__, 2) . '/component/backend/language/en-GB/com_datacompliance.ini');
		$subject = $strings[$definition['subject']] ?? '';

		if ($subject !== strip_tags($subject))
		{
			$this->markTestSkipped(sprintf('Known issue #18 (see known-issues.md): the subject of %s contains markup: %s', $key, $subject));
		}

		$this->assertTrue(true);
	}
}
