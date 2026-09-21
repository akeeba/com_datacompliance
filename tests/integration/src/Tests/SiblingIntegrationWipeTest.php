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
 * What a wipe does to the data of the Akeeba extensions Data Compliance integrates with:
 * plg_datacompliance_ats (Akeeba Ticket System) and plg_datacompliance_ars (Akeeba Release System).
 *
 * Both are real installs, built from the sibling working copies by run.sh; the plugins act on the
 * real tables.
 *
 * By design (see the triage notes on M4 / L21): user and admin wipes delete the user's whole tickets,
 * public ones included, with every post; lifecycle wipes leave public tickets alone.
 *
 * @since 4.1.0
 */
class SiblingIntegrationWipeTest extends AbstractE2ETestCase
{
	/**
	 * Set up: no administrator notifications; they are tested in WipeNotificationTest.
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
	 * A user wipe deletes the user's tickets — private and public — with all their posts and
	 * attachments; other people's tickets are left alone.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testUserWipeDeletesTheUsersTickets(): void
	{
		$this->requireSibling('ats');

		[$victimId, $ats] = $this->victimWithAtsData();
		$surfer           = $this->frontendLogin((string) $this->userRow($victimId)['username']);

		$response = $this->requestWipe($surfer);

		$this->assertNotServerError($response);
		$this->assertCount(1, $this->wipeTrails($victimId), 'The wipe did not happen.');

		foreach (['privateTicket', 'publicTicket'] as $ticket)
		{
			$this->assertSame(0, $this->rows('#__ats_tickets', 'id', $ats[$ticket]), sprintf('The %s survived the wipe.', $ticket));
		}

		foreach (['ownPost', 'staffPost', 'publicPost'] as $post)
		{
			$this->assertSame(0, $this->rows('#__ats_posts', 'id', $ats[$post]), sprintf('The %s survived the wipe.', $post));
		}

		$this->assertSame(0, $this->rows('#__ats_attachments', 'id', $ats['attachment']), 'The attachment record survived the wipe.');

		// Someone else's ticket, and their post in it, stay.
		$this->assertSame(1, $this->rows('#__ats_tickets', 'id', $ats['othersTicket']), 'Another user\'s ticket was deleted.');
		$this->assertSame(1, $this->rows('#__ats_posts', 'id', $ats['othersPost']), 'Another user\'s post was deleted.');

		// The audit trail says what was deleted.
		$items = json_decode((string) $this->wipeTrails($victimId)[0]['items'], true);

		$this->assertEqualsCanonicalizing(
			[$ats['privateTicket'], $ats['publicTicket']],
			array_map('intval', $items['ats']['tickets'] ?? []),
			'The audit trail does not list the deleted tickets.'
		);

		$file = static::$config->getSiteRoot() . '/' . $ats['attachmentFile'];

		$this->assertFileDoesNotExist(
			$file,
			'The attachment FILE survives the wipe (' . $ats['attachmentFile'] . '): plg_datacompliance_ats deletes the #__ats_attachments rows with a DELETE query, bypassing ATS\'s AttachmentTable, which is what removes the file from disk.'
		);

		$this->assertSame(
			0,
			$this->rows('#__ats_managernotes', 'id', $ats['managerNote']),
			'The manager notes of the deleted tickets survive the wipe, orphaned: plg_datacompliance_ats deletes tickets, posts and attachments, but not #__ats_managernotes — notes staff write ABOUT the user.'
		);

		$this->assertOrKnownIssue(
			$this->rows('#__ats_tickets_users', 'id', $ats['invite']) === 0,
			22,
			'The user\'s invitations to other people\'s tickets (#__ats_tickets_users) survive the wipe, and are not exported either.'
		);
	}

	/**
	 * A lifecycle wipe leaves the user's PUBLIC tickets in place (by design: support staff make any
	 * ticket with identifying information private), and deletes the private ones.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testLifecycleWipeKeepsPublicTickets(): void
	{
		$this->requireSibling('ats');

		[$victimId, $ats] = $this->victimWithAtsData([
			'lastvisitDate' => gmdate('Y-m-d H:i:s', strtotime('-3 years')),
			'registerDate'  => gmdate('Y-m-d H:i:s', strtotime('-4 years')),
		]);

		// Notified, a day ago, that the account is to be deleted as of a minute ago.
		foreach (['datacompliance.notified' => '1', 'datacompliance.notified_on' => gmdate('Y-m-d H:i:s', time() - 86400), 'datacompliance.notified_for' => gmdate('Y-m-d H:i:s', time() - 60)] as $key => $value)
		{
			$this->db()->query(
				'INSERT INTO #__user_profiles (user_id, profile_key, profile_value, ordering) VALUES (?, ?, ?, 0)',
				[$victimId, $key, $value]
			);
		}

		[$exitCode, $output] = $this->cli()->joomla(['datacompliance:lifecycle:delete']);

		$this->assertSame(0, $exitCode, $output);
		$this->assertSame('lifecycle', $this->wipeTrails($victimId)[0]['type'] ?? null, "The lifecycle wipe did not happen.\n" . $output);

		$this->assertSame(0, $this->rows('#__ats_tickets', 'id', $ats['privateTicket']), 'The private ticket survived the lifecycle wipe.');
		$this->assertSame(1, $this->rows('#__ats_tickets', 'id', $ats['publicTicket']), 'The public ticket was deleted by the lifecycle wipe.');
		$this->assertSame(1, $this->rows('#__ats_posts', 'id', $ats['publicPost']), 'The post of the public ticket was deleted by the lifecycle wipe.');
	}

	/**
	 * A wipe deletes the user's ARS download log and Download IDs.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testWipeDeletesArsRecords(): void
	{
		$this->requireSibling('ars');

		$victimId = static::$fixtures->createUser();
		$ars      = static::$fixtures->seedArsFor($victimId);
		$other    = static::$fixtures->getManifest()['ars']['alice'];

		$response = $this->requestWipe($this->loggedIn('wiper'), $victimId);

		$this->assertNotServerError($response);
		$this->assertCount(1, $this->wipeTrails($victimId), 'The wipe did not happen.');

		$this->assertSame(0, $this->rows('#__ars_log', 'id', $ars['log']), 'The download log survived the wipe.');
		$this->assertSame(0, $this->rows('#__ars_dlidlabels', 'id', $ars['dlid']), 'The Download ID survived the wipe.');

		// Alice's records are not the victim's.
		$this->assertSame(1, $this->rows('#__ars_dlidlabels', 'id', $other['dlid']), 'Another user\'s Download ID was deleted.');
	}

	/**
	 * A throwaway account with its own ATS tickets.
	 *
	 * @param   array  $spec  createUser() settings.
	 *
	 * @return  array{0: int, 1: array}  The id, and the ATS fixture ids.
	 * @since   4.1.0
	 */
	private function victimWithAtsData(array $spec = []): array
	{
		$id = static::$fixtures->createUser($spec);

		return [$id, static::$fixtures->seedAtsFor($id)];
	}

	/**
	 * How many rows of a table have the given key.
	 *
	 * @param   string  $table   The table, with #__.
	 * @param   string  $column  The key column.
	 * @param   int     $value   The key.
	 *
	 * @return  int
	 * @since   4.1.0
	 */
	private function rows(string $table, string $column, int $value): int
	{
		return (int) $this->db()->value(sprintf('SELECT COUNT(*) FROM %s WHERE `%s` = ?', $table, $column), [$value]);
	}
}
