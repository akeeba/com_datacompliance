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
 * Account lifecycle management: which accounts are end-of-life, the Lifecycle view, and the
 * datacompliance:lifecycle:notify / datacompliance:lifecycle:delete console commands.
 *
 * The commands run through Joomla's real console application inside the php container, exactly as a
 * cron job would.
 *
 * Regression tests for H4 (switching the lifecycle rules off did not stop them), L17 (the Lifecycle
 * list cache) and I6 (lifecycle notifications never sent).
 *
 * The lifecycle commands act on EVERY end-of-life account on the site, so this class puts the
 * fixtures back when it is done.
 *
 * @since 4.1.0
 */
class LifecycleTest extends AbstractE2ETestCase
{
	/**
	 * Tear down the class: fixtures back to the start.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public static function tearDownAfterClass(): void
	{
		static::$fixtures->reset();

		parent::tearDownAfterClass();
	}

	/**
	 * Set up: plg_datacompliance_joomla's lifecycle rules at their defaults, and no administrator
	 * notifications, so that the only mail in the mailbox is the users' (the defaults are covered by
	 * testLifecycleDeleteWithDefaultNotificationSettings; regression test for known issue #11).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function setUp(): void
	{
		parent::setUp();

		static::$fixtures->setPluginParams('datacompliance', 'joomla', ['lifecycle' => 1, 'threshold' => 18, 'nevervisited' => 1, 'blocked' => 1]);
		static::$fixtures->setPluginParams('datacompliance', 'email', ['users' => 1, 'admins' => 0]);
		$this->mailpit()->clear();
	}

	/**
	 * Tear down: notifications back to their defaults.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['users' => 1, 'admins' => 1]);
		static::$fixtures->setPluginParams('datacompliance', 'joomla', ['lifecycle' => 1]);

		parent::tearDown();
	}

	/**
	 * The Lifecycle view lists the accounts the default rules consider end-of-life, and not the others.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testLifecycleViewListsEndOfLifeAccounts(): void
	{
		$users = $this->lifecycleUsers();
		$page  = $this->lifecyclePage();

		foreach (['stale', 'neverVisited', 'blockedOld'] as $kind)
		{
			$this->assertBodyContains($users[$kind]['username'], $page, sprintf('The %s account is not listed as end-of-life.', $kind));
		}

		foreach (['active', 'blockedRecent'] as $kind)
		{
			$this->assertBodyNotContains($users[$kind]['username'], $page, sprintf('The %s account is listed as end-of-life.', $kind));
		}

		$this->assertBodyNotContains(
			$users['freshNeverVisited']['username'],
			$page,
			'An account registered an hour ago that has not logged in yet is listed as end-of-life, and will be deleted by the next datacompliance:lifecycle:delete once notified: the "never visited" rule of plg_datacompliance_joomla ignores the registration date (the "blocked" rule honours it).'
		);
	}

	/**
	 * With the "never visited" rule off, the "blocked" rule still matches a blocked account that never
	 * logged in — unless it was registered within the threshold.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testBlockedRuleMatchesAccountsThatNeverVisited(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'joomla', ['nevervisited' => 0]);

		$old    = $this->createLifecycleUser('blockednever', ['block' => 1, 'lastvisitDate' => null, 'registerDate' => $this->yearsAgo(4)]);
		$recent = $this->createLifecycleUser('blockedfresh', ['block' => 1, 'lastvisitDate' => null, 'registerDate' => gmdate('Y-m-d H:i:s', time() - 3600)]);
		$page   = $this->lifecyclePage();

		$this->assertBodyContains($old['username'], $page, 'A blocked account registered years ago that never logged in is not listed as end-of-life: NOT (lastvisitDate >= …) is NULL, not TRUE, for a NULL last visit date.');
		$this->assertBodyNotContains($recent['username'], $page, 'A blocked account registered an hour ago is listed as end-of-life.');
	}

	/**
	 * H4: switching the lifecycle rules off reports nobody.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testLifecycleOffReportsNobody(): void
	{
		$users = $this->lifecycleUsers();

		// Control: the stale account IS listed while the rules are on.
		$this->assertBodyContains($users['stale']['username'], $this->lifecyclePage());

		static::$fixtures->setPluginParams('datacompliance', 'joomla', ['lifecycle' => 0]);

		$page = $this->lifecyclePage();

		foreach (['stale', 'neverVisited', 'blockedOld'] as $kind)
		{
			$this->assertBodyNotContains($users[$kind]['username'], $page, sprintf('The %s account is still listed with the lifecycle rules off.', $kind));
		}

		// And the delete command, with --force (no notification needed), deletes nobody.
		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:lifecycle:delete', '--force']);

		$this->assertSame(0, $exitCode, $output);
		$this->assertSame($users['stale']['username'], $this->userRow($users['stale']['id'])['username'], "The lifecycle rules are off, yet the account was deleted.\n" . $output);
	}

	/**
	 * The Lifecycle view is for core.manage holders only.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testLifecycleViewNeedsCoreManage(): void
	{
		$nomanage = $this->loggedInBackend('nomanage');

		$this->assertRefused($nomanage, $nomanage->get($this->adminUrl(['view' => 'lifecycle'])));
	}

	/**
	 * notify, then delete: the notified end-of-life account is warned by email and then wiped as a
	 * 'lifecycle' wipe; an end-of-life account that was NOT notified is left alone.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testNotifyThenDelete(): void
	{
		$users = $this->lifecycleUsers();
		$stale = $users['stale'];

		// I6: notify. P0D: "your account will be deleted as of now".
		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:lifecycle:notify', '--period=P0D']);

		$this->assertSame(0, $exitCode, $output);
		$this->assertSame(1, (int) $this->db()->value(
			"SELECT profile_value FROM #__user_profiles WHERE user_id = ? AND profile_key = 'datacompliance.notified'",
			[$stale['id']]
		), "The stale account was not marked as notified.\n" . $output);

		$warnings = $this->mailpit()->messagesTo($stale['email']);

		$this->assertCount(1, $warnings, "The stale account was not emailed a lifecycle warning.\n" . $output);
		$this->assertStringNotContainsString('{', $this->mailpit()->message($warnings[0]['ID'])['Subject'], 'The warning subject has unreplaced tags.');

		// Notified accounts are not notified twice.
		[, $again] = $this->cli()->joomla(['datacompliance:lifecycle:notify', '--period=P0D']);
		$this->assertCount(1, $this->mailpit()->messagesTo($stale['email']), "The stale account was warned twice.\n" . $again);

		// An end-of-life account that appears AFTER the notification round must not be deleted yet.
		$late = $this->createLifecycleUser('lateStale', ['lastvisitDate' => $this->yearsAgo(3), 'registerDate' => $this->yearsAgo(4)]);

		$this->mailpit()->clear();

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:lifecycle:delete']);

		$this->assertSame(0, $exitCode, $output);

		$after = $this->userRow($stale['id']);

		$this->assertNotSame($stale['username'], $after['username'], "The notified end-of-life account was not deleted.\n" . $output);

		$trails = $this->wipeTrails($stale['id']);

		$this->assertCount(1, $trails);
		$this->assertSame('lifecycle', $trails[0]['type']);

		$this->assertSame($late['username'], $this->userRow($late['id'])['username'], "An end-of-life account that was never notified was deleted.\n" . $output);

		// The view lists a stale Super User as end-of-life (it matches the rules), but the commands must
		// neither warn nor delete one: plg_datacompliance_joomla refuses to delete Super Users.
		$super = $users['staleSuper'];

		$this->assertSame($super['username'], $this->userRow($super['id'])['username'], "A stale Super User was deleted.\n" . $output);
		$this->assertSame(0, (int) $this->db()->value(
			"SELECT COUNT(*) FROM #__user_profiles WHERE user_id = ? AND profile_key = 'datacompliance.notified'",
			[$super['id']]
		), 'A stale Super User was warned that their account will be deleted.');

		// The deletion notice to the user.
		$notices = $this->mailpit()->messagesTo($stale['email']);

		$this->assertCount(1, $notices, "The user was not told their account was deleted.\n" . $output);

		$subject = $this->mailpit()->message($notices[0]['ID'])['Subject'];

		$this->assertTrue(
			!preg_grep('/Undefined variable/', $this->newPhpErrors()) && !str_contains($output, 'Failed to delete:       ' . $late['id']),
			"datacompliance:lifecycle:delete's report is wrong: it increments and prints an undefined \$cannotNotify (\"PHP Warning: Undefined variable \$cannotNotify\" in php-errors.log), labels the skipped accounts with the summary line \"Failed to delete: %u\" (COM_DATACOMPLIANCE_CLI_LIFECYCLEDELETE_LBL_NOTNOTIFIED is defined twice in the language file; the second definition wins), and never reports the accounts it failed to delete (\$cannotDelete).\nOutput:\n" . $output
		);
		$this->assertStringContainsString(sprintf('User %d is not notified, skipping.', $late['id']), $output, "The account that was never notified is not reported as skipped.\n" . $output);
		$this->assertStringContainsString('Failed to delete:       0', $output, "The summary does not report the number of failed deletions.\n" . $output);

		$this->assertOrKnownIssue(
			!str_contains($subject, '<code>'),
			18,
			sprintf('The subject of the lifecycle deletion email sent to the user contains HTML and the name of a CLI command: "%s" (COM_DATACOMPLIANCE_MAIL_USER_LIFECYCLE_SUBJECT looks copied from the template\'s description).', $subject)
		);
	}

	/**
	 * --dry-run deletes nothing and sends nothing.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDryRunChangesNothing(): void
	{
		$stale = $this->lifecycleUsers()['stale'];

		$this->cli()->joomlaOrFail(['datacompliance:lifecycle:notify', '--period=P0D', '--dry-run']);

		$this->assertNull($this->db()->value(
			"SELECT profile_value FROM #__user_profiles WHERE user_id = ? AND profile_key = 'datacompliance.notified'",
			[$stale['id']]
		), 'A dry run marked an account as notified.');

		// Mark it notified for real, then dry-run the deletion.
		$this->cli()->joomlaOrFail(['datacompliance:lifecycle:notify', '--period=P0D']);
		$this->mailpit()->clear();

		$this->cli()->joomlaOrFail(['datacompliance:lifecycle:delete', '--dry-run']);

		$this->assertSame($stale['username'], $this->userRow($stale['id'])['username'], 'A dry run deleted an account.');
		$this->assertSame([], $this->wipeTrails($stale['id']));
		$this->assertSame(0, $this->mailpit()->count(), 'A dry run sent email.');
	}

	/**
	 * Logging in resets the lifecycle notification: a user who comes back is no longer end-of-life,
	 * and must not be deleted on the strength of an old warning.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testLoggingInClearsTheNotification(): void
	{
		$stale = $this->lifecycleUsers()['stale'];

		$this->cli()->joomlaOrFail(['datacompliance:lifecycle:notify', '--period=P0D']);

		$this->assertSame(1, (int) $this->db()->value(
			"SELECT COUNT(*) FROM #__user_profiles WHERE user_id = ? AND profile_key = 'datacompliance.notified'",
			[$stale['id']]
		), 'The account was not notified; the test would prove nothing.');

		$this->frontendLogin($stale['username']);

		$this->assertSame(0, (int) $this->db()->value(
			"SELECT COUNT(*) FROM #__user_profiles WHERE user_id = ? AND profile_key LIKE 'datacompliance.notified%'",
			[$stale['id']]
		), 'Logging in did not clear the lifecycle notification.');

		$this->cli()->joomlaOrFail(['datacompliance:lifecycle:delete']);

		$this->assertSame($stale['username'], $this->userRow($stale['id'])['username'], 'A user who came back was deleted.');
	}

	/**
	 * With the email plugin's defaults, the lifecycle deletion completes.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testLifecycleDeleteWithDefaultNotificationSettings(): void
	{
		$stale = $this->lifecycleUsers()['stale'];

		$this->cli()->joomlaOrFail(['datacompliance:lifecycle:notify', '--period=P0D']);

		static::$fixtures->setPluginParams('datacompliance', 'email', ['users' => 1, 'admins' => 1]);

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:lifecycle:delete']);

		$this->assertTrue(
			$exitCode === 0 && $this->userRow($stale['id'])['username'] !== $stale['username'],
			sprintf("With the email plugin's defaults datacompliance:lifecycle:delete dies on the first account (exit %d) and leaves it NOT pseudonymised but with a wipe audit record — the same TypeError as on the web.\nOutput:\n%s", $exitCode, $output)
		);
	}

	/**
	 * Create the set of accounts the lifecycle tests observe.
	 *
	 * @return  array<string, array{id: int, username: string, email: string}>
	 * @since   4.1.0
	 */
	private function lifecycleUsers(): array
	{
		return [
			'stale'             => $this->createLifecycleUser('stale', ['lastvisitDate' => $this->yearsAgo(3), 'registerDate' => $this->yearsAgo(4)]),
			'neverVisited'      => $this->createLifecycleUser('never', ['lastvisitDate' => null, 'registerDate' => $this->yearsAgo(3)]),
			'freshNeverVisited' => $this->createLifecycleUser('fresh', ['lastvisitDate' => null, 'registerDate' => gmdate('Y-m-d H:i:s', time() - 3600)]),
			'blockedOld'        => $this->createLifecycleUser('blockedold', ['block' => 1, 'lastvisitDate' => $this->yearsAgo(3), 'registerDate' => $this->yearsAgo(4)]),
			'blockedRecent'     => $this->createLifecycleUser('blockedrecent', ['block' => 1, 'lastvisitDate' => gmdate('Y-m-d H:i:s', time() - 86400), 'registerDate' => $this->yearsAgo(4)]),
			'active'            => $this->createLifecycleUser('active', ['lastvisitDate' => gmdate('Y-m-d H:i:s', time() - 86400), 'registerDate' => $this->yearsAgo(4)]),
			'staleSuper'        => $this->createLifecycleUser('stalesuper', ['lastvisitDate' => $this->yearsAgo(3), 'registerDate' => $this->yearsAgo(4), 'groups' => [8]]),
		];
	}

	/**
	 * Create one throwaway account with the given dates.
	 *
	 * @param   string  $label  Part of the username, for readable failures.
	 * @param   array   $spec   createUser() settings.
	 *
	 * @return  array{id: int, username: string, email: string}
	 * @since   4.1.0
	 */
	private function createLifecycleUser(string $label, array $spec): array
	{
		$username = 'lc' . $label . bin2hex(random_bytes(3));
		$id       = static::$fixtures->createUser($spec + ['username' => $username]);

		return ['id' => $id, 'username' => $username, 'email' => $username . '@example.test'];
	}

	/**
	 * The Lifecycle view as an Administrator, showing every end-of-life account.
	 *
	 * @return  \Akeeba\DataCompliance\IntegrationTest\Engine\Response
	 * @since   4.1.0
	 */
	private function lifecyclePage(): \Akeeba\DataCompliance\IntegrationTest\Engine\Response
	{
		$administrator = $this->loggedInBackend('administrator');
		// Filtered to "expired" accounts; unfiltered, the view lists every account on the site.
		$page          = $administrator->get($this->adminUrl(['view' => 'lifecycle', 'filter[lifecycle]' => 1, 'list[limit]' => 0]));

		$this->assertStatus(200, $page);

		return $page;
	}

	/**
	 * An SQL datetime N years ago.
	 *
	 * @param   int  $years  How many years.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private function yearsAgo(int $years): string
	{
		return gmdate('Y-m-d H:i:s', strtotime(sprintf('-%d years', $years)));
	}
}
