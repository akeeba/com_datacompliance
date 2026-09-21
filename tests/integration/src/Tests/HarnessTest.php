<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest\Tests;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\IntegrationTest\AbstractE2ETestCase;

/**
 * The harness checks itself before anything relies on it.
 *
 * A permissions test that passes because the fixture accidentally granted the wrong thing is worse
 * than no test at all, so the ACL matrix the other tests lean on is asserted here, through the real
 * site's own authorisation code (the identity probe), not assumed from what the provisioner meant to
 * do.
 *
 * @since 4.1.0
 */
class HarnessTest extends AbstractE2ETestCase
{
	/**
	 * The site is up, and the version it reports is the one run.sh says it installed.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testSiteIsTheOneWeProvisioned(): void
	{
		$identity = $this->session->probeIdentity($this->guest());

		$this->assertIsArray($identity, 'The identity probe did not answer.');
		$this->assertTrue($identity['guest'], 'A fresh surfer must be a guest.');

		$expected = static::$config->getJoomlaVersion();

		if ($expected !== '0.0.0')
		{
			$this->assertStringStartsWith($expected, (string) $identity['joomla']);
		}
	}

	/**
	 * Data Compliance, its plugins and (when requested) ATS and ARS are installed and enabled the way
	 * the provisioner says.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testExtensionsAreInstalled(): void
	{
		$enabled = fn(string $folder, string $element): ?int => ($value = $this->db()->value(
			"SELECT enabled FROM #__extensions WHERE type = 'plugin' AND folder = ? AND element = ?",
			[$folder, $element]
		)) === null ? null : (int) $value;

		$this->assertSame(
			1,
			(int) $this->db()->value("SELECT enabled FROM #__extensions WHERE type = 'component' AND element = 'com_datacompliance'")
		);

		foreach ([['system', 'datacompliance'], ['user', 'datacompliance'], ['console', 'datacompliance'], ['datacompliance', 'joomla'], ['datacompliance', 'email']] as [$folder, $element])
		{
			$this->assertSame(1, $enabled($folder, $element), sprintf('plg_%s_%s must be enabled.', $folder, $element));
		}

		$manifest = static::$fixtures->getManifest();

		$this->assertSame(static::$config->hasSibling('ats'), (bool) $manifest['hasAts'], 'ATS installed state does not match config.php.');
		$this->assertSame(static::$config->hasSibling('ars'), (bool) $manifest['hasArs'], 'ARS installed state does not match config.php.');
	}

	/**
	 * Every role logs in and is who it claims to be, and the ACL matrix is what the fixture claims.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testAclMatrixIsWhatItClaims(): void
	{
		$actions = ['export', 'wipe', 'core.admin', 'core.manage'];

		// role => [export, wipe, core.admin on the component, core.manage on the component]
		$expected = [
			'alice'    => [false, false, false, false],
			'exporter' => [true, false, false, false],
			'wiper'    => [false, true, false, false],
			'dcadmin'  => [false, false, true, true],
			'administrator' => [false, false, false, true],
			'nomanage' => [false, false, false, false],
			'super2'   => [true, true, true, true],
		];

		foreach ($expected as $role => $grants)
		{
			$identity = $this->session->probeIdentity($this->loggedIn($role), ['com_datacompliance'], $actions);

			$this->assertIsArray($identity, sprintf('The identity probe did not answer for %s.', $role));
			$this->assertSame(static::$fixtures->username($role), $identity['username']);

			foreach ($actions as $i => $action)
			{
				$this->assertSame(
					$grants[$i],
					$identity['authorise']['com_datacompliance'][$action],
					sprintf('Role %s: %s on com_datacompliance should be %s.', $role, $action, $grants[$i] ? 'granted' : 'denied')
				);
			}
		}

		// nomanage is refused by the explicit Deny, not by never having had the privilege: on any other
		// component it has core.manage, inherited like every Administrator's.
		$identity = $this->session->probeIdentity($this->loggedIn('nomanage'), ['com_users'], ['core.manage', 'core.login.admin']);
		$this->assertTrue($identity['authorise']['com_users']['core.manage'], 'nomanage must inherit core.manage elsewhere.');

		// The DC Administrator is NOT a Super User: core.admin on the root asset is denied.
		$identity = $this->session->probeIdentity($this->loggedIn('dcadmin'), ['root.1'], ['core.admin']);
		$this->assertFalse($identity['authorise']['root.1']['core.admin'], 'dcadmin must not be a Super User.');
	}

	/**
	 * Only carol lacks a consent record; everybody else would otherwise be stuck on the consent page.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testConsentFixtures(): void
	{
		$this->assertNull($this->consentRow(static::$fixtures->userId('carol')));

		foreach (['admin', 'alice', 'bob', 'exporter', 'wiper', 'dcadmin', 'administrator', 'nomanage', 'super2', 'exempt'] as $role)
		{
			$this->assertSame(1, (int) ($this->consentRow(static::$fixtures->userId($role))['enabled'] ?? 0), $role . ' must have consented.');
		}
	}

	/**
	 * Mailpit and MinIO answer.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testAuxiliaryServicesAreUp(): void
	{
		$this->assertTrue($this->mailpit()->isAvailable(), 'Mailpit is not reachable.');

		[$exitCode, $output] = $this->cli()->mc(['ls', 'e2e/' . static::$config->getS3()['bucket']]);

		$this->assertSame(0, $exitCode, "The S3 bucket is not reachable:\n" . $output);
	}
}
