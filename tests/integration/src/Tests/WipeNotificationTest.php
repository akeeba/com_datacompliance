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
 * The notifications plg_datacompliance_email sends when an account is wiped.
 *
 * Joomla delivers them over real SMTP to Mailpit, through Joomla's MailTemplate (and the component's
 * MailTemplateHotFix, which is on by default), so what is asserted here is what a user would find in
 * their inbox.
 *
 * Regression tests for L4 (the administrator allow-list, and the Super User lookup that used to match
 * nobody) and L5 (user data escaped in HTML mail).
 *
 * @since 4.1.0
 */
class WipeNotificationTest extends AbstractE2ETestCase
{
	/**
	 * Set up: an empty mailbox.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->mailpit()->clear();
	}

	/**
	 * Tear down: the email plugin back to its defaults.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['users' => 1, 'admins' => 1, 'adminemails' => '']);
		static::$fixtures->setComponentParams(['mail_style' => 'plaintext'], 'com_mails');

		parent::tearDown();
	}

	/**
	 * With the email plugin's default settings (notify users AND administrators), a wipe completes.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWipeCompletesWithDefaultNotificationSettings(): void
	{
		[$victimId, $username] = $this->victimWipesThemselves();

		$after  = $this->userRow($victimId);
		$wiped  = $after['username'] !== $username;
		$trails = count($this->wipeTrails($victimId));

		$this->assertOrKnownIssue(
			$wiped && $this->lastWipeResponseCode < 500,
			11,
			sprintf(
				'With the email plugin\'s defaults every wipe dies with an HTTP %d, after the wipe audit record is written (%d record(s)) but BEFORE the account is pseudonymised (%s). plg_datacompliance_email passes the stdClass rows of getSuperUserEmails() to TemplateEmails::sendMail(string, array, ?User …): a TypeError. The account can then never be wiped again, because its audit record already exists. The same happens in datacompliance:lifecycle:delete and datacompliance:account:delete.',
				$this->lastWipeResponseCode,
				$trails,
				$wiped ? 'pseudonymised' : 'NOT pseudonymised'
			)
		);

		// Only reached once the TypeError is fixed: the administrators were told.
		$this->assertNotEmpty($this->mailpit()->messagesTo(static::$fixtures->email('admin')), 'The Super User was not notified.');
	}

	/**
	 * The user is emailed at the address they had before the wipe, addressed by their real name, with
	 * the list of actions taken. L5: their name is escaped in the HTML part.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testUserIsNotifiedAtTheirOriginalAddress(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['users' => 1, 'admins' => 0]);
		// Joomla's mail templates are plain text by default; the HTML part is what L5 is about.
		static::$fixtures->setComponentParams(['mail_style' => 'both'], 'com_mails');

		$hostileName = 'E2E <b>Bold</b> Victim';

		[$victimId, $username] = $this->victimWipesThemselves(['name' => $hostileName]);

		$this->assertNotSame($username, $this->userRow($victimId)['username'], 'The account was not wiped.');

		$messages = $this->mailpit()->messagesTo($username . '@example.test');

		$this->assertCount(1, $messages, 'The user did not receive exactly one notification at their original address.');

		$message = $this->mailpit()->message($messages[0]['ID']);

		$this->assertStringContainsString('Data Compliance End-to-End', $message['Subject'], 'The site name is missing from the subject.');
		$this->assertStringNotContainsString('{', $message['Subject'], 'The subject has unreplaced tags.');
		$this->assertStringContainsString($hostileName, $message['Text'], 'The plain text part does not address the user by name.');
		$this->assertStringContainsString('pseudonymised', $message['Text'], 'The list of actions is missing.');
		$this->assertStringNotContainsString('{NAME}', $message['Text'] . $message['HTML'], 'Unreplaced tags in the body.');

		// The actions list (trusted language strings) is HTML.
		$this->assertStringContainsString('<li>', $message['HTML'], 'The actions list is not rendered as HTML.');

		// No administrator notifications when they are switched off.
		$this->assertSame([], $this->mailpit()->messagesTo(static::$fixtures->email('admin')));

		// L5: user data is escaped in HTML. Last, because it skips.
		$this->assertOrKnownIssue(
			!str_contains($message['HTML'], '<b>Bold</b>') && str_contains($message['HTML'], '&lt;b&gt;Bold&lt;/b&gt;'),
			14,
			'The user\'s name is raw HTML in the HTML notification: the L5 fix relies on MailTemplate::addUnsafeTags(), but with com_mails\' HTML layout enabled (the default) core Joomla\'s MailTemplate::send() replaces the tags a second time, in the rendered layout, without escaping. Data Compliance must escape the values itself.'
		);
	}

	/**
	 * No notification to the user when that is switched off.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testNoUserNotificationWhenDisabled(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['users' => 0, 'admins' => 0]);

		[$victimId, $username] = $this->victimWipesThemselves();

		$this->assertNotSame($username, $this->userRow($victimId)['username'], 'The account was not wiped.');
		$this->assertSame(0, $this->mailpit()->count(), 'Mail was sent although both notifications are off.');
	}

	/**
	 * L4: the administrators notified are the Super Users who accept system emails, filtered by the
	 * allow-list when one is given; and each is addressed as themselves.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testAdministratorAllowList(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', [
			'users' => 0, 'admins' => 1, 'adminemails' => "\n  " . static::$fixtures->email('super2') . "  \n",
		]);

		[$victimId, $username, $name] = $this->victimWipesThemselves();

		$this->assertOrKnownIssue(
			$this->lastWipeResponseCode < 500,
			11,
			'Administrator notifications crash the wipe (see testWipeCompletesWithDefaultNotificationSettings).'
		);

		$this->assertCount(1, $this->mailpit()->messagesTo(static::$fixtures->email('super2')), 'The allow-listed Super User was not notified.');
		$this->assertSame([], $this->mailpit()->messagesTo(static::$fixtures->email('admin')), 'A Super User outside the allow-list was notified.');

		$message = $this->mailpit()->message($this->mailpit()->messagesTo(static::$fixtures->email('super2'))[0]['ID']);

		$this->assertStringContainsString((string) $victimId, $message['Text'], 'The notification does not say which user was deleted.');
		$this->assertOrKnownIssue(
			!str_contains($message['Text'], 'Hello ' . $name),
			13,
			'The administrator notifications greet the Super User with the DELETED user\'s name ("Hello {NAME}"): the admin_* templates use {NAME}, which is the wiped user\'s name; the recipient\'s is {ADMIN:NAME}.'
		);
	}

	/**
	 * The HTTP status of the last wipe request.
	 *
	 * @var   int
	 * @since 4.1.0
	 */
	private int $lastWipeResponseCode = 0;

	/**
	 * Create a throwaway account and have it wipe itself.
	 *
	 * @param   array  $spec  Extra createUser() settings.
	 *
	 * @return  array{0: int, 1: string, 2: string}  Its id, its original username and name.
	 * @since   4.1.0
	 */
	private function victimWipesThemselves(array $spec = []): array
	{
		$id       = static::$fixtures->createUser($spec);
		$row      = $this->userRow($id);
		$surfer   = $this->frontendLogin($row['username']);
		$response = $this->requestWipe($surfer);

		$this->lastWipeResponseCode = $response->code;

		return [$id, $row['username'], $row['name']];
	}
}
