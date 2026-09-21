<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\UnitTest\Helper;

use Akeeba\Component\DataCompliance\Administrator\Helper\VersionLimits;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;

/**
 * VersionLimits, the runtime guard every entry point (dispatcher, plugin service providers) calls, and
 * its agreement with the installer and composer.json.
 *
 * The supported range is declared once, in composer.json's extra.akcompat, and stamped into three
 * places by the release tooling. If any of them drifts, the installer, the runtime and the build would
 * disagree about what a supported site is.
 *
 * @since 4.1.0
 */
#[CoversClass(VersionLimits::class)]
class VersionLimitsTest extends TestCase
{
	/**
	 * The original static state of VersionLimits, restored after each test.
	 *
	 * @var   array<string, mixed>
	 * @since 4.1.0
	 */
	private array $saved = [];

	/**
	 * Set up: remember VersionLimits' static state.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function setUp(): void
	{
		foreach (['minPHPVersion', 'maxPHPVersion', 'minJoomlaVersion', 'maxJoomlaVersion', 'incompatibleReason'] as $property)
		{
			$this->saved[$property] = $this->getStatic($property);
		}
	}

	/**
	 * Tear down: restore VersionLimits' static state.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		foreach ($this->saved as $property => $value)
		{
			$this->setStatic($property, $value);
		}
	}

	/**
	 * The declared bounds match composer.json and the installer script.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testBoundsMatchComposerAndInstaller(): void
	{
		$root     = \dirname(__DIR__, 2);
		$composer = json_decode(file_get_contents($root . '/composer.json'), true);
		$script   = file_get_contents($root . '/component/script.datacompliance.php');

		$this->assertTrue(
			(bool) preg_match('/^>=\s*([0-9.]+)\s+<\s*([0-9.]+)$/', $composer['require']['php'], $php),
			'composer.json require.php is not ">=MIN <MAX".'
		);
		$this->assertTrue(
			(bool) preg_match('/^>=\s*([0-9.]+)\s+<\s*([0-9.]+)$/', $composer['extra']['akcompat']['limit'], $joomla),
			'composer.json extra.akcompat.limit is not ">=MIN <MAX".'
		);

		$installer = fn(string $name): ?string => preg_match('/\$' . $name . '\s*=\s*\'([0-9.]+)\'/', $script, $m) ? $m[1] : null;

		$this->assertSame($php[1], $this->getStatic('minPHPVersion'), 'VersionLimits minimum PHP');
		$this->assertSame($php[2], $this->getStatic('maxPHPVersion'), 'VersionLimits maximum PHP');
		$this->assertSame($joomla[1], $this->getStatic('minJoomlaVersion'), 'VersionLimits minimum Joomla');
		$this->assertSame($joomla[2], $this->getStatic('maxJoomlaVersion'), 'VersionLimits maximum Joomla');

		$this->assertSame($php[1], $installer('minimumPhp'), 'installer minimum PHP');
		$this->assertSame($php[2], $installer('maximumPhp'), 'installer maximum PHP');
		$this->assertSame($joomla[1], $installer('minimumJoomla'), 'installer minimum Joomla');
		$this->assertSame($joomla[2], $installer('maximumJoomla'), 'installer maximum Joomla');

		// The build pins the platform to the highest PHP the range allows.
		$this->assertTrue(
			version_compare($composer['config']['platform']['php'], $php[1], 'ge')
			&& version_compare($composer['config']['platform']['php'], $php[2], 'lt'),
			'composer.json config.platform.php is outside the supported range.'
		);
	}

	/**
	 * composer.lock was generated for the composer.json it sits next to. A stale lock makes `phing git`
	 * fail at `composer install`.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testComposerLockMatchesComposerJson(): void
	{
		$root     = \dirname(__DIR__, 2);
		$composer = json_decode(file_get_contents($root . '/composer.json'), true);
		$lock     = json_decode(file_get_contents($root . '/composer.lock'), true);

		$this->assertSame(
			$composer['config']['platform']['php'] ?? null,
			$lock['platform-overrides']['php'] ?? null,
			'Known issue #1: composer.lock is stale (its platform override differs from composer.json); run `composer update --lock`.'
		);
		$this->assertSame(
			$composer['require']['php'],
			$lock['platform']['php'] ?? null,
			'Known issue #1: composer.lock is stale (its PHP requirement differs from composer.json); run `composer update --lock`.'
		);
	}

	/**
	 * Versions inside, at and outside the bounds. The maxima are exclusive.
	 *
	 * @return  array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: string}>
	 * @since   4.1.0
	 */
	public static function rangeCases(): array
	{
		// minPHP, maxPHP, minJoomla, maxJoomla (all relative to PHP_VERSION and the bootstrap's JVERSION 6.1.3), compatible, why
		return [
			'inside'              => ['8.0.0', '99.0', '5.4.0', '6.3', true, ''],
			'PHP at the minimum'  => [PHP_VERSION, '99.0', '5.4.0', '6.3', true, ''],
			'PHP too low'         => ['99.0.0', '99.9', '5.4.0', '6.3', false, 'requires PHP 99.0.0'],
			'PHP at the maximum'  => ['8.0.0', PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION, '5.4.0', '6.3', false, 'only compatible with PHP versions lower than'],
			'Joomla at minimum'   => ['8.0.0', '99.0', '6.1.3', '6.3', true, ''],
			'Joomla too low'      => ['8.0.0', '99.0', '6.2.0', '6.3', false, 'requires Joomla 6.2.0'],
			'Joomla at maximum'   => ['8.0.0', '99.0', '5.4.0', '6.1', false, 'only compatible with Joomla versions lower than 6.1'],
		];
	}

	/**
	 * isCompatible() and throwIfVersionsIncompatible() agree, and the exception says what is wrong.
	 *
	 * @param   string  $minPhp      Minimum PHP.
	 * @param   string  $maxPhp      Exclusive maximum PHP.
	 * @param   string  $minJoomla   Minimum Joomla.
	 * @param   string  $maxJoomla   Exclusive maximum Joomla.
	 * @param   bool    $compatible  Expected result.
	 * @param   string  $message     Expected fragment of the exception message.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	#[DataProvider('rangeCases')]
	public function testRange(string $minPhp, string $maxPhp, string $minJoomla, string $maxJoomla, bool $compatible, string $message): void
	{
		$this->setStatic('minPHPVersion', $minPhp);
		$this->setStatic('maxPHPVersion', $maxPhp);
		$this->setStatic('minJoomlaVersion', $minJoomla);
		$this->setStatic('maxJoomlaVersion', $maxJoomla);
		// The results are cached per request; start from a clean cache.
		$this->setStatic('incompatibleReason', []);

		$this->assertSame($compatible, VersionLimits::isCompatible());

		if ($compatible)
		{
			VersionLimits::throwIfVersionsIncompatible();

			return;
		}

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage($message);

		VersionLimits::throwIfVersionsIncompatible();
	}

	/**
	 * Read a private static property of VersionLimits.
	 *
	 * @param   string  $name  The property.
	 *
	 * @return  mixed
	 * @since   4.1.0
	 */
	private function getStatic(string $name)
	{
		return (new ReflectionClass(VersionLimits::class))->getProperty($name)->getValue();
	}

	/**
	 * Write a private static property of VersionLimits.
	 *
	 * @param   string  $name   The property.
	 * @param   mixed   $value  The value.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function setStatic(string $name, $value): void
	{
		(new ReflectionClass(VersionLimits::class))->getProperty($name)->setValue(null, $value);
	}
}
