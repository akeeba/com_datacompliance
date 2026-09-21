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
 * The datacompliance:account:delete console command.
 *
 * Its exit codes are part of its contract — scripts act on them — so they are asserted exactly:
 * 0 deleted, 1 dry run, 127 refused or failed, 254 no such user, 255 no user given.
 *
 * @since 4.1.0
 */
class AccountDeleteCliTest extends AbstractE2ETestCase
{
	/**
	 * Set up: no administrator notifications, so that these tests do not depend on mail delivery
	 * (testDeleteWithDefaultNotificationSettings covers the defaults; regression test for known issue #11).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function setUp(): void
	{
		parent::setUp();

		static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 0]);
	}

	/**
	 * Tear down: notifications back to their defaults.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 1]);

		parent::tearDown();
	}

	/**
	 * Delete by username, and by id: the account is wiped, as an 'admin' wipe.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDeleteByUsernameAndById(): void
	{
		foreach (['username', 'id'] as $by)
		{
			$id     = static::$fixtures->createUser();
			$before = $this->userRow($id);

			[$exitCode, $output] = $this->cli()->joomla([
				'datacompliance:account:delete',
				$by === 'id' ? '--id=' . $id : '--username=' . $before['username'],
			]);

			$this->assertSame(0, $exitCode, $output);
			$this->assertNotSame($before['username'], $this->userRow($id)['username'], "The account was not wiped.\n" . $output);

			$trails = $this->wipeTrails($id);

			$this->assertCount(1, $trails);
			$this->assertSame('admin', $trails[0]['type']);
		}
	}

	/**
	 * --dry-run deletes nothing.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDryRun(): void
	{
		$id     = static::$fixtures->createUser();
		$before = $this->userRow($id);

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete', '--id=' . $id, '--dry-run']);

		$this->assertSame(1, $exitCode, $output);
		$this->assertUserUntouched($before, 'The dry run deleted the account.');
	}

	/**
	 * A Super User, a back-end user, and an exempt account are refused (exit 127) and left untouched.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testProtectedAccountsAreRefused(): void
	{
		foreach (['super2', 'administrator', 'exempt'] as $role)
		{
			$before = $this->userRow(static::$fixtures->userId($role));

			[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete', '--id=' . $before['id']]);

			$this->assertSame(127, $exitCode, $role . "\n" . $output);
			$this->assertUserUntouched($before, $role . ' was deleted.');
		}
	}

	/**
	 * --force skips the checks: a back-end account is deleted.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testForceDeletesAProtectedAccount(): void
	{
		$id     = static::$fixtures->createUser(['groups' => [static::$fixtures->groupId('administrator')]]);
		$before = $this->userRow($id);

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete', '--id=' . $id, '--force']);

		$this->assertSame(0, $exitCode, $output);
		$this->assertNotSame($before['username'], $this->userRow($id)['username'], "--force did not delete the account.\n" . $output);
	}

	/**
	 * No user, and a user that does not exist.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testBadArguments(): void
	{
		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete']);
		$this->assertSame(255, $exitCode, $output);

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete', '--username=nobody-' . bin2hex(random_bytes(4))]);
		$this->assertSame(254, $exitCode, $output);

		$missing             = (int) $this->db()->value('SELECT MAX(id) FROM #__users') + 1000;
		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete', '--id=' . $missing]);
		$this->assertSame(254, $exitCode, $output);
		$this->assertSame([], $this->wipeTrails($missing));
	}

	/**
	 * With the email plugin's defaults the command completes.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDeleteWithDefaultNotificationSettings(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 1]);

		$id     = static::$fixtures->createUser();
		$before = $this->userRow($id);

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:account:delete', '--id=' . $id]);

		$this->assertTrue(
			$exitCode === 0 && $this->userRow($id)['username'] !== $before['username'],
			sprintf("With the email plugin's defaults datacompliance:account:delete dies (exit %d), leaving the account NOT pseudonymised but with a wipe audit record.\nOutput:\n%s", $exitCode, $output)
		);
	}
}
