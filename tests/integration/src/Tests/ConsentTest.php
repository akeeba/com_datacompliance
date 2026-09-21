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

/**
 * Consent: the captive redirect of plg_system_datacompliance, and recording a preference.
 *
 * Covers the consent redirect for a user without consent, recording and withdrawing consent, the
 * anti-CSRF check (L2), recording consent on someone else's behalf (only Super Users and
 * DataCompliance administrators, with evidence: L8, and the Article 7(1) evidence requirement), and a
 * non-existent target (I7).
 *
 * @since 4.1.0
 */
class ConsentTest extends AbstractE2ETestCase
{
	/**
	 * A logged-in user without consent is sent to the consent page from anywhere on the site, but may
	 * still reach the consent page itself and log out.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testUserWithoutConsentIsRedirectedToTheConsentPage(): void
	{
		$userId = static::$fixtures->createUser(['consent' => false]);
		$surfer = $this->loginFresh($userId);

		$response = $surfer->get('index.php', ['option' => 'com_content', 'view' => 'featured']);

		$this->assertTrue($response->isRedirect(), "An unconsented user reached a content page.\n" . $response->summary());
		$this->assertStringContainsString('option=com_datacompliance', (string) $response->getLocation());
		$this->assertStringContainsString('view=options', (string) $response->getLocation());

		// The consent page itself renders.
		$page = $surfer->get($this->siteUrl(['view' => 'options']));

		$this->assertStatus(200, $page);
		$this->assertBodyContains('E2E-PRIVACY-POLICY-INTRO', $page);
		$this->assertBodyContains('name="enabled"', $page, 'The consent form is missing.');

		// And a guest is never redirected.
		$guest = $this->guest()->get('index.php', ['option' => 'com_content', 'view' => 'featured']);

		$this->assertFalse($guest->isRedirect(), 'A guest was redirected to the consent page.');
	}

	/**
	 * Giving consent records it, and ends the redirect; withdrawing it brings the redirect back.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testRecordAndWithdrawConsent(): void
	{
		$userId = static::$fixtures->createUser(['consent' => false]);
		$surfer = $this->loginFresh($userId);

		$response = $this->requestConsent($surfer, true);

		$this->assertNotServerError($response, 'Recording consent');

		$row = $this->consentRow($userId);

		$this->assertNotNull($row, 'Consent was not recorded.');
		$this->assertSame(1, (int) $row['enabled']);
		$this->assertNotSame('', (string) $row['requester_ip']);

		$this->assertFalse(
			$surfer->get('index.php', ['option' => 'com_content', 'view' => 'featured'])->isRedirect(),
			'The consent redirect continued after consenting.'
		);

		// Withdraw.
		$this->requestConsent($surfer, false);

		$this->assertSame(0, (int) $this->consentRow($userId)['enabled']);
		$this->assertTrue(
			$surfer->get('index.php', ['option' => 'com_content', 'view' => 'featured'])->isRedirect(),
			'Withdrawing consent must bring the consent redirect back.'
		);
	}

	/**
	 * L2: consent needs a POST token.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testConsentNeedsAToken(): void
	{
		$userId = static::$fixtures->createUser(['consent' => false]);
		$surfer = $this->loginFresh($userId);

		$response = $this->requestConsent($surfer, true, null, null, '');

		$this->assertRefused($surfer, $response, 'Consent without a token');
		$this->assertNull($this->consentRow($userId), 'Consent was recorded without a token.');

		// A token in the query string instead of the body does not count either.
		$token    = $this->tokenFor($surfer);
		$response = $surfer->post($this->siteUrl(['view' => 'options', 'task' => 'consent', $token => 1]), ['enabled' => 1]);

		$this->assertRefused($surfer, $response, 'Consent with the token in the URL');
		$this->assertNull($this->consentRow($userId), 'Consent was recorded with the token in the URL.');
	}

	/**
	 * A DataCompliance administrator (core.admin on the component, not a Super User) may record consent
	 * on someone else's behalf, with evidence; their own consent record is left alone.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDcAdministratorRecordsConsentOnBehalfWithEvidence(): void
	{
		$subject = static::$fixtures->createUser(['consent' => false]);
		$actorId = static::$fixtures->userId('dcadmin');
		$before  = $this->consentRow($actorId);
		$actor   = $this->loggedIn('dcadmin');

		// The form is offered.
		$page = $actor->get($this->siteUrl(['view' => 'options', 'user_id' => $subject]));

		$this->assertStatus(200, $page);
		$this->assertBodyContains('name="reason"', $page, 'The on-behalf consent form is missing for a DC administrator.');

		// Without evidence: refused, nothing recorded.
		$response = $this->requestConsent($actor, true, $subject, '   ');

		$this->assertNotServerError($response);
		$this->assertNull($this->consentRow($subject), 'Consent was recorded on behalf of someone without evidence.');

		// With evidence.
		$response = $this->requestConsent($actor, true, $subject, 'Signed paper form #E2E-42');

		$this->assertNotServerError($response);

		$row = $this->consentRow($subject);

		$this->assertNotNull($row, 'Consent was not recorded on behalf of the subject.');
		$this->assertSame(1, (int) $row['enabled']);
		$this->assertSame($before, $this->consentRow($actorId), "The actor's own consent record changed.");

		$this->assertOrKnownIssue(
			($row['reason'] ?? null) === 'Signed paper form #E2E-42',
			2,
			'The Article 7(1) evidence of consent is silently discarded on a fresh install: install.mysql.utf8.sql has no `reason` column (only the 4.0.2 update SQL adds it), and DatabaseDriver::insertObject() drops properties without a column.'
		);
	}

	/**
	 * L8: export and wipe are the wrong privileges for recording someone else's consent, and so is
	 * core.manage. The form is not offered, and a forged request is refused.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testOnlySuperUsersAndDcAdministratorsRecordConsentOnBehalf(): void
	{
		$subject = static::$fixtures->createUser(['consent' => false]);

		foreach (['alice', 'exporter', 'wiper', 'administrator'] as $role)
		{
			$actor    = $this->loggedIn($role);
			$response = $this->requestConsent($actor, true, $subject, 'I say so');

			$this->assertRefused($actor, $response, $role . ' recording consent for someone else');
			$this->assertNull($this->consentRow($subject), sprintf('%s recorded consent on behalf of another user.', $role));

			if (in_array($role, ['exporter', 'wiper'], true))
			{
				$page = $actor->get($this->siteUrl(['view' => 'options', 'user_id' => $subject]));

				$this->assertStatus(200, $page, $role . ' may view the other user\'s Options page');
				$this->assertBodyNotContains('name="reason"', $page, $role . ' is offered the on-behalf consent form.');
			}
		}
	}

	/**
	 * I7: consent for a user id that does not exist is a 404, and must never land on the actor's own
	 * consent record.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testConsentForMissingUserDoesNotTouchTheActor(): void
	{
		$actorId = static::$fixtures->userId('dcadmin');
		$before  = $this->consentRow($actorId);
		$actor   = $this->loggedIn('dcadmin');
		$missing = 1 + (int) $this->db()->value('SELECT MAX(id) FROM #__users') + 1000;

		$response = $this->requestConsent($actor, false, $missing, 'Evidence for nobody');

		$this->assertStatus(404, $response);
		$this->assertSame($before, $this->consentRow($actorId), "The actor's consent record changed.");
		$this->assertNull($this->consentRow($missing));
	}

	/**
	 * Consent given through Joomla's own privacy consent is transcribed into Data Compliance, instead
	 * of asking the user a second time.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testJoomlaPrivacyConsentIsTranscribed(): void
	{
		$userId = static::$fixtures->createUser(['consent' => false]);

		$this->db()->insert('#__privacy_consents', [
			'user_id' => $userId,
			'state'   => 1,
			'created' => gmdate('Y-m-d H:i:s'),
			'subject' => 'PLG_SYSTEM_PRIVACYCONSENT_SUBJECT',
			'body'    => 'E2E',
			'remind'  => 0,
			'token'   => '',
		]);

		$surfer   = $this->loginFresh($userId);
		$response = $surfer->get('index.php', ['option' => 'com_content', 'view' => 'featured']);

		$this->assertFalse($response->isRedirect(), "A user who consented through Joomla was asked again.\n" . $response->summary());
		$this->assertSame(1, (int) ($this->consentRow($userId)['enabled'] ?? 0), 'The Joomla consent was not transcribed.');
	}

	/**
	 * Log a throwaway account into the front-end, WITHOUT following the post-login redirect (which,
	 * for an unconsented user, is the consent redirect the tests want to observe themselves).
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	private function loginFresh(int $userId): Surfer
	{
		$username = (string) $this->db()->value('SELECT username FROM #__users WHERE id = ?', [$userId]);

		return $this->frontendLogin($username);
	}
}
