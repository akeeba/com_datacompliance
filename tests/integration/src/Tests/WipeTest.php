<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest\Tests;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\IntegrationTest\AbstractE2ETestCase;
use Akeeba\DataCompliance\IntegrationTest\Engine\Surfer;
use RuntimeException;

/**
 * Account erasure (the right to be forgotten) from the Options page.
 *
 * Every wipe here targets a throwaway account: erasure is irreversible, and the shared accounts are
 * needed by every other test.
 *
 * Regression tests for M1 (core.manage standing in for the wipe privilege), L2 (token in URLs, phrase
 * from POST only), L6 (only refusal reasons reach the user), I5 (the wipe type) and the protection of
 * Super Users, back-end users and exempt groups; plus what a wipe must actually do to the account.
 *
 * @since 4.1.0
 */
class WipeTest extends AbstractE2ETestCase
{
	/**
	 * Set up: no administrator notifications.
	 *
	 * The notifications have their own test class (WipeNotificationTest, which also covers known
	 * issue #11: administrator notifications used to crash every wipe half-way); here they are
	 * switched off so that what the wipe does to the account is tested independently of mail.
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
	 * The confirmation page renders, lists what will happen, needs no token, and puts none in a URL.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testConfirmationPage(): void
	{
		[$victimId, $surfer] = $this->victim();

		$page = $surfer->get($this->siteUrl(['view' => 'options', 'task' => 'wipe']));

		$this->assertStatus(200, $page);
		$this->assertBodyContains('name="phrase"', $page, 'The confirmation phrase field is missing.');
		$this->assertBodyContains('pseudonymised', $page, 'The list of actions (plg_datacompliance_joomla) is missing.');
		$this->assertNoTokenInLinks($page);

		// The Options page links to it without a token, too.
		$this->assertNoTokenInLinks($surfer->get($this->siteUrl(['view' => 'options'])));

		// Merely looking changed nothing.
		$this->assertSame([], $this->wipeTrails($victimId));
	}

	/**
	 * A wrong phrase, a missing token, or the phrase in the query string: nothing is wiped.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWipeIsRefusedWithoutPhraseAndToken(): void
	{
		[$victimId, $surfer] = $this->victim();
		$before              = $this->userRow($victimId);

		$response = $this->requestWipe($surfer, null, 'I DO NOT UNDERSTAND');

		$this->assertRefused($surfer, $response, 'Wrong phrase');
		$this->assertUserUntouched($before, 'Wrong phrase.');

		$response = $this->requestWipe($surfer, null, self::WIPE_PHRASE, false, '');

		$this->assertRefused($surfer, $response, 'No token');
		$this->assertUserUntouched($before, 'No token.');

		// The phrase and the token in the URL: the phrase is read from POST only, so this is merely the
		// confirmation page.
		$token    = $this->tokenFor($surfer);
		$response = $surfer->get($this->siteUrl(['view' => 'options', 'task' => 'wipe', 'phrase' => self::WIPE_PHRASE, $token => 1]));

		$this->assertStatus(200, $response);
		$this->assertBodyContains('name="phrase"', $response);
		$this->assertUserUntouched($before, 'Phrase and token in the URL.');
	}

	/**
	 * A user wipes their own account: it is pseudonymised, its personal data removed, its privileges
	 * stripped, the session ended, and the wipe recorded as a 'user' wipe.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testSelfWipePseudonymisesAndRemovesPersonalData(): void
	{
		[$victimId, $surfer, $username] = $this->victim();
		$data                           = static::$fixtures->seedPersonalData($victimId);
		$before                         = $this->userRow($victimId);

		$response = $this->requestWipe($surfer);

		$this->assertNotServerError($response);
		$this->assertTrue($response->isRedirect(), "A successful wipe redirects to the home page.\n" . $response->summary());

		$after = $this->userRow($victimId);

		/**
		 * Pseudonymised, not deleted: the audit trail and other tables may still reference the id.
		 *
		 * The exact pseudonyms are not asserted. plg_datacompliance_joomla writes "user<id>" / "User <id>",
		 * and then WipeModel runs Joomla's own privacy plugins, whose plg_privacy_user pseudonymises the
		 * same account again (random username, "UserID<id>removed@email.invalid"). What matters is that
		 * nothing identifying is left.
		 */
		$this->assertNotNull($after, 'The user row was deleted rather than pseudonymised.');
		$this->assertWiped($before, $after);
		$this->assertStringStartsWith('$', $after['password'], 'The new password is not a password hash (I8).');
		$this->assertStringStartsWith('1999-01-01', (string) $after['registerDate']);

		// Recorded as a self-service wipe.
		$trails = $this->wipeTrails($victimId);

		$this->assertCount(1, $trails, 'Exactly one wipe audit trail record.');
		$this->assertSame('user', $trails[0]['type']);
		$this->assertStringNotContainsString($before['email'], (string) $trails[0]['items'], 'The audit trail holds personal data.');

		// Logged out, and the old credentials are dead.
		$this->assertNull($this->session->getCurrentUsername($surfer), 'The wiped user is still logged in.');

		try
		{
			$this->frontendLogin($username);
			$this->fail('The wiped account can still log in with its old username and password.');
		}
		catch (RuntimeException $e)
		{
			$this->assertStringContainsString('Could not log in', $e->getMessage());
		}

		// The personal data the wipe page promises to remove.
		$this->assertSame(0, (int) $this->db()->value('SELECT COUNT(*) FROM #__user_notes WHERE user_id = ?', [$victimId]), 'User notes survived.');
		$this->assertSame(0, (int) $this->db()->value('SELECT COUNT(*) FROM #__user_profiles WHERE user_id = ?', [$victimId]), 'Profile fields survived.');

		$groups     = (int) $this->db()->value('SELECT COUNT(*) FROM #__user_usergroup_map WHERE user_id = ?', [$victimId]);
		$keys       = (int) $this->db()->value('SELECT COUNT(*) FROM #__user_keys WHERE series = ?', [$data['keySeries']]);
		$fieldValue = (int) $this->db()->value('SELECT COUNT(*) FROM #__fields_values WHERE item_id = ? AND value = ?', [(string) $victimId, $data['fieldValue']]);

		$this->assertSame(0, $keys, 'The user\'s remember-me key (#__user_keys) survives the wipe: deleteKeys() matches user_id against the numeric id, but that column holds the username.');

		$this->assertSame(0, $groups, sprintf('The wiped account still belongs to %d user group(s). plg_datacompliance_joomla deletes its #__user_usergroup_map rows, then pseudonymizeUser() calls $user->save() on the User object it loaded BEFORE that, and Table\\User::store() writes the stale $groups straight back.', $groups));

		$this->assertSame(
			0,
			$fieldValue,
			'The value of the user\'s custom field (#__fields_values, com_users.user context) survives the wipe: deleteFields() only deletes #__user_profiles rows, although the wipe page promises that "any additional user profile fields will be removed".'
		);
	}

	/**
	 * Accounts that must never be wiped from the Options page, even by their owners: Super Users,
	 * back-end users, and members of an exempt group. Refused with the plugin's reason (L6), and left
	 * untouched.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testProtectedAccountsCannotWipeThemselves(): void
	{
		$cases = [
			'exempt'        => [$this->loggedIn('exempt'), 'exempt from being deleted'],
			'administrator' => [$this->loggedIn('administrator'), 'administrator login access'],
			'admin'         => [$this->superUserFrontend(), 'Super User'],
		];

		foreach ($cases as $role => [$surfer, $reason])
		{
			$before   = $this->userRow(static::$fixtures->userId($role));
			$response = $this->requestWipe($surfer);

			$this->assertNotServerError($response, $role);
			$this->assertTrue($response->isRedirect(), $role . ": a refused wipe redirects back.\n" . $response->summary());

			$surfer->followRedirects = true;
			$landing                 = $surfer->get((string) $response->getLocation());
			$surfer->followRedirects = false;

			$this->assertBodyContains('cannot be deleted automatically', $landing, $role . ': the refusal is not shown.');
			$this->assertBodyContains(
				$reason,
				$landing,
				$role . ': the refusal does not say why. The plugin must refuse with a WipeRefusedException, whose message WipeModel::getRefusalReason() hands to the Options page.'
			);
			$this->assertBodyNotContains('internal error', $landing, $role);
			$this->assertUserUntouched($before, $role . ' wiped their own account.');
		}
	}

	/**
	 * Wiping someone else needs the `wipe` privilege (or core.admin on the component). Plain users,
	 * exporters and Administrators — whose core.manage used to stand in for it (M1) — are refused.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testOthersCannotBeWipedWithoutThePrivilege(): void
	{
		[$victimId] = $this->victim();
		$before     = $this->userRow($victimId);

		foreach (['alice', 'exporter', 'administrator'] as $role)
		{
			$actor    = $this->loggedIn($role);
			$response = $this->requestWipe($actor, $victimId);

			$this->assertRefused($actor, $response, $role . ' wiping someone else');
			$this->assertUserUntouched($before, $role . ' wiped someone else.');

			// Nor may they even open the confirmation page for someone else.
			$response = $actor->get($this->siteUrl(['view' => 'options', 'task' => 'wipe', 'user_id' => $victimId]));

			$this->assertRefused($actor, $response, $role . ' opening the wipe page of someone else');
		}

		// The Administrator again, from the back-end, where core.manage lets them into the component.
		$administrator = $this->loggedInBackend('administrator');
		$response      = $this->requestWipe($administrator, $victimId, self::WIPE_PHRASE, true);

		$this->assertRefused($administrator, $response, 'administrator wiping someone else from the back-end');
		$this->assertUserUntouched($before, 'administrator wiped someone else from the back-end.');
	}

	/**
	 * The `wipe` privilege wipes another account, recorded as an 'admin' wipe (I5).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWiperWipesAnotherUser(): void
	{
		[$victimId] = $this->victim();
		$before     = $this->userRow($victimId);

		$response = $this->requestWipe($this->loggedIn('wiper'), $victimId);

		$this->assertNotServerError($response);
		$this->assertWiped($before, $this->userRow($victimId));

		$trails = $this->wipeTrails($victimId);

		$this->assertCount(1, $trails);
		$this->assertSame('admin', $trails[0]['type'], 'Wiping someone else must be recorded as an admin wipe.');

		// The wiper is still logged in: only a SELF wipe ends the session.
		$this->assertSame('wiper', $this->session->getCurrentUsername($this->loggedIn('wiper')));
	}

	/**
	 * A DataCompliance administrator (core.admin on the component) may wipe others too.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDcAdministratorWipesAnotherUser(): void
	{
		[$victimId] = $this->victim();

		$before     = $this->userRow($victimId);
		$response   = $this->requestWipe($this->loggedIn('dcadmin'), $victimId);

		$this->assertNotServerError($response);
		$this->assertWiped($before, $this->userRow($victimId));
	}

	/**
	 * Only Super Users may wipe Super Users; back-end users are protected by plg_datacompliance_joomla
	 * (by design, see the triage notes on L7).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWiperCannotWipeProtectedAccounts(): void
	{
		$wiper = $this->loggedIn('wiper');

		foreach (['super2', 'administrator'] as $role)
		{
			$before   = $this->userRow(static::$fixtures->userId($role));
			$response = $this->requestWipe($wiper, (int) $before['id']);

			$this->assertNotServerError($response, $role);
			$this->assertRefused($wiper, $response, 'wiper wiping ' . $role);
			$this->assertUserUntouched($before, 'wiper wiped ' . $role . '.');
		}

		/**
		 * The Super User is refused by the controller's access check (HTTP 403). The back-end user passes it, and is
		 * refused by plg_datacompliance_joomla, whose reason must reach the wiper.
		 */
		$response = $this->requestWipe($wiper, static::$fixtures->userId('administrator'));

		$this->assertTrue($response->isRedirect(), "A refused wipe redirects back.\n" . $response->summary());

		$wiper->followRedirects = true;
		$landing                = $wiper->get((string) $response->getLocation());
		$wiper->followRedirects = false;

		$this->assertBodyContains('administrator login access', $landing, 'The refusal does not say why.');
		$this->assertBodyNotContains('internal error', $landing);
	}

	/**
	 * L6: an account that has already been wiped cannot be wiped again, and the user is told why —
	 * the refusal reason, not an internal error.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testAlreadyWipedAccountIsRefusedWithTheReason(): void
	{
		[$victimId] = $this->victim();
		$wiper      = $this->loggedIn('wiper');

		$this->requestWipe($wiper, $victimId);
		$this->assertCount(1, $this->wipeTrails($victimId), 'The first wipe did not happen.');

		$response = $this->requestWipe($wiper, $victimId);

		$this->assertNotServerError($response);
		$this->assertTrue($response->isRedirect(), $response->summary());

		$wiper->followRedirects = true;
		$landing                = $wiper->get((string) $response->getLocation());
		$wiper->followRedirects = false;

		$this->assertBodyContains('has already been deleted', $landing, 'The refusal reason is not shown.');
		$this->assertBodyNotContains('internal error', $landing);
		$this->assertCount(1, $this->wipeTrails($victimId), 'A second wipe audit trail record was written.');
	}

	/**
	 * Wiping a user id that does not exist is refused cleanly: no crash, and no audit record of a
	 * wipe that never happened.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWipeOfMissingUser(): void
	{
		$missing  = (int) $this->db()->value('SELECT MAX(id) FROM #__users') + 1000;
		$wiper    = $this->loggedIn('wiper');
		$response = $this->requestWipe($wiper, $missing);

		$trails = (int) $this->db()->value('SELECT COUNT(*) FROM #__datacompliance_wipetrails WHERE user_id IN (0, ?)', [$missing]);

		$this->assertTrue(
			$response->code < 500 && $trails === 0,
			sprintf(
				'Wiping a user id that does not exist gives HTTP %d and %d wipe audit record(s): OptionsController::wipe() passes the empty User object\'s null id on to WipeModel::checkWipeAbility(int …) — a TypeError, which is an Error, not an Exception, so safeWipeModelCall() does not catch it.',
				$response->code,
				$trails
			)
		);
	}

	/**
	 * Assert that none of the identifying columns of an account survived its wipe.
	 *
	 * @param   array  $before  The user row before the wipe.
	 * @param   array  $after   The user row after it.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function assertWiped(array $before, array $after): void
	{
		$this->assertNotSame($before['username'], $after['username'], 'The username was not pseudonymised.');
		$this->assertNotSame($before['name'], $after['name'], 'The name was not pseudonymised.');
		$this->assertNotSame($before['email'], $after['email'], 'The email address was not pseudonymised.');

		foreach (['username', 'name', 'email'] as $column)
		{
			$this->assertStringNotContainsString(
				strtolower($before['username']),
				strtolower((string) $after[$column]),
				sprintf('The %s still identifies the user.', $column)
			);
		}

		$this->assertNotSame($before['password'], $after['password'], 'The password was not changed.');
	}

	/**
	 * A throwaway account, logged into the front-end.
	 *
	 * @param   array  $spec  Extra createUser() settings.
	 *
	 * @return  array{0: int, 1: Surfer, 2: string}  Its id, a logged-in surfer, and its username.
	 * @since   4.1.0
	 */
	private function victim(array $spec = []): array
	{
		$id       = static::$fixtures->createUser($spec);
		$username = (string) $this->db()->value('SELECT username FROM #__users WHERE id = ?', [$id]);

		return [$id, $this->frontendLogin($username), $username];
	}
}
