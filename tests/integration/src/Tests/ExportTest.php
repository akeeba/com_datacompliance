<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest\Tests;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\IntegrationTest\AbstractE2ETestCase;
use Akeeba\DataCompliance\IntegrationTest\Engine\Response;
use SimpleXMLElement;

/**
 * The personal data export (GDPR data portability).
 *
 * Regression tests for H2 (credential material in the export; exporting Super Users), M1 (core.manage
 * standing in for the export privilege), L2 (anti-CSRF token in URLs) and L3 (cache headers on a
 * response full of personal data), plus the export audit trail.
 *
 * @since 4.1.0
 */
class ExportTest extends AbstractE2ETestCase
{
	/**
	 * Tear down: the Maximalist Export tests change the component options.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		static::$fixtures->setComponentParams(['maximalist_export' => 1]);

		parent::tearDown();
	}

	/**
	 * A user exports their own data: a well-formed XML attachment with their data in it, the right
	 * headers, never the password hash, and an audit trail record.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testSelfExport(): void
	{
		$aliceId = static::$fixtures->userId('alice');
		$trails  = $this->exportTrailCount($aliceId);

		$response = $this->requestExport($this->loggedIn('alice'));
		$xml      = $this->assertIsExport($response);

		$this->assertStringContainsString(static::$fixtures->email('alice'), $response->body);
		$this->assertSame(['users', 'user_notes', 'user_profiles', 'user_usergroup_map', 'user_keys'], array_values(array_intersect(
			['users', 'user_notes', 'user_profiles', 'user_usergroup_map', 'user_keys'],
			$this->domainNames($xml)
		)), 'The Joomla core domains are missing from the export.');

		// H2: the password hash never leaves the site.
		$hash = (string) $this->db()->value('SELECT password FROM #__users WHERE id = ?', [$aliceId]);

		$this->assertStringNotContainsString($hash, $response->body, 'The export contains the password hash.');
		$this->assertSame([], $this->columnValues($xml, 'users', 'password'), 'The export has a password column.');

		// L3: nobody may cache it.
		$cacheControl = strtolower((string) $response->getHeader('cache-control'));

		$this->assertStringContainsString('no-store', $cacheControl);
		$this->assertStringContainsString('private', $cacheControl);
		$this->assertStringNotContainsString('public', $cacheControl);
		$this->assertSame('nosniff', strtolower((string) $response->getHeader('x-content-type-options')));
		$this->assertStringContainsString('attachment', (string) $response->getHeader('content-disposition'));

		$this->assertSame($trails + 1, $this->exportTrailCount($aliceId), 'The export was not recorded in the audit trail.');
	}

	/**
	 * The back-end Options page exports too.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testSelfExportFromTheBackend(): void
	{
		$super    = $this->superUser();
		$response = $this->requestExport($super, null, true);

		$this->assertIsExport($response);
		$this->assertStringContainsString(static::$fixtures->email('admin'), $response->body);
	}

	/**
	 * L2: the export needs a POST token; one in the URL does not count.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testExportNeedsAPostToken(): void
	{
		$aliceId = static::$fixtures->userId('alice');
		$trails  = $this->exportTrailCount($aliceId);
		$alice   = $this->loggedIn('alice');

		$response = $this->requestExport($alice, null, false, '');

		$this->assertRefused($alice, $response, 'Export without a token');
		$this->assertStringNotContainsString(static::$fixtures->email('alice'), $response->body);

		$token    = $this->tokenFor($alice);
		$response = $alice->get($this->siteUrl(['view' => 'options', 'task' => 'export', 'format' => 'raw', $token => 1]));

		$this->assertRefused($alice, $response, 'Export with the token in the URL');
		$this->assertStringNotContainsString('<domain', $response->body);

		$this->assertSame($trails, $this->exportTrailCount($aliceId), 'A refused export was recorded in the audit trail.');

		// And the Options page never puts the token in a link.
		$this->assertNoTokenInLinks($alice->get($this->siteUrl(['view' => 'options'])));
	}

	/**
	 * Someone holding the `export` privilege exports another (non Super User) account.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testExporterExportsAnotherUser(): void
	{
		$bobId  = static::$fixtures->userId('bob');
		$trails = $this->exportTrailCount($bobId);

		$response = $this->requestExport($this->loggedIn('exporter'), $bobId);

		$this->assertIsExport($response);
		$this->assertStringContainsString(static::$fixtures->email('bob'), $response->body);
		$this->assertStringNotContainsString(static::$fixtures->email('exporter'), $response->body, 'The export is of the actor, not the target.');
		$this->assertSame($trails + 1, $this->exportTrailCount($bobId));
	}

	/**
	 * Without the export privilege, nobody exports another account. That includes an Administrator
	 * whose core.manage on the component used to stand in for it (M1), and the wipe privilege.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testOthersCannotBeExportedWithoutThePrivilege(): void
	{
		$bobId  = static::$fixtures->userId('bob');
		$trails = $this->exportTrailCount($bobId);

		foreach (['alice', 'wiper', 'administrator'] as $role)
		{
			$actor    = $this->loggedIn($role);
			$response = $this->requestExport($actor, $bobId);

			$this->assertRefused($actor, $response, $role . ' exporting bob');
			$this->assertStringNotContainsString(static::$fixtures->email('bob'), $response->body, $role . ' received bob\'s data.');
		}

		// The same Administrator, in the back-end, where core.manage lets them into the component.
		$administrator = $this->loggedInBackend('administrator');
		$response      = $this->requestExport($administrator, $bobId, true);

		$this->assertRefused($administrator, $response, 'administrator exporting bob from the back-end');
		$this->assertStringNotContainsString(static::$fixtures->email('bob'), $response->body);

		$this->assertSame($trails, $this->exportTrailCount($bobId), 'A refused export was recorded in the audit trail.');
	}

	/**
	 * H2a: only Super Users may export Super Users.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testOnlySuperUsersExportSuperUsers(): void
	{
		$superId = static::$fixtures->userId('super2');
		$trails  = $this->exportTrailCount($superId);

		foreach (['exporter', 'dcadmin'] as $role)
		{
			$actor    = $this->loggedIn($role);
			$response = $this->requestExport($actor, $superId);

			$this->assertRefused($actor, $response, $role . ' exporting a Super User');
			$this->assertStringNotContainsString(static::$fixtures->email('super2'), $response->body);
		}

		$this->assertSame($trails, $this->exportTrailCount($superId), 'A refused export was recorded in the audit trail.');

		// The control: a Super User may.
		$response = $this->requestExport($this->superUserFrontend(), $superId);

		$this->assertIsExport($response);
		$this->assertStringContainsString(static::$fixtures->email('super2'), $response->body);
	}

	/**
	 * H2a, the user interface half: the Options page of a Super User offers no export to a non Super
	 * User, since the controller would refuse it anyway.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testOptionsPageOfASuperUserOffersNoExportToAnExporter(): void
	{
		$superId  = static::$fixtures->userId('super2');
		$page     = $this->loggedIn('exporter')->get($this->siteUrl(['view' => 'options', 'user_id' => $superId]));
		$offered  = (bool) preg_match('/name="user_id"\s+value="' . $superId . '"/', $page->body);

		$this->assertStatus(200, $page);
		$this->assertFalse(
			$offered,
			'The Options page of a Super User still offers the Export (and Delete) buttons to a non Super User with the export/wipe privilege, although the controller refuses that action (H2a). HtmlView::$canManageExport / $canManageWipe must be false in this case.'
		);
	}

	/**
	 * DataCompliance administrators (core.admin on the component) may export other users.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testDcAdministratorExportsAnotherUser(): void
	{
		$response = $this->requestExport($this->loggedIn('dcadmin'), static::$fixtures->userId('bob'));

		$this->assertIsExport($response);
		$this->assertStringContainsString(static::$fixtures->email('bob'), $response->body);
	}

	/**
	 * H2b: with Maximalist Export off, authentication material is removed at the source: the
	 * remember-me series and token, the API token seed, the activation token, the legacy TFA columns
	 * (Joomla 5 only) and, when ARS is installed, all but the last four characters of Download IDs.
	 * With it on (the default), they are exported — but never the password hash.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testMaximalistExportControlsAuthenticationMaterial(): void
	{
		$userId = static::$fixtures->createUser(['activation' => 'e2eactivation' . bin2hex(random_bytes(8))]);
		$data   = static::$fixtures->seedPersonalData($userId);
		$seed   = 'E2E-API-SEED-' . bin2hex(random_bytes(8));

		$this->db()->query(
			'INSERT INTO #__user_profiles (user_id, profile_key, profile_value, ordering) VALUES (?, ?, ?, 2)',
			[$userId, 'joomlatoken.token', $seed]
		);

		$activation = (string) $this->db()->value('SELECT activation FROM #__users WHERE id = ?', [$userId]);
		$hash       = (string) $this->db()->value('SELECT password FROM #__users WHERE id = ?', [$userId]);
		$dlid       = null;

		if (static::$fixtures->getManifest()['hasArs'])
		{
			$dlid = static::$fixtures->seedArsFor($userId)['dlidValue'];
		}

		$username = (string) $this->db()->value('SELECT username FROM #__users WHERE id = ?', [$userId]);
		$surfer   = $this->frontendLogin($username);

		// Maximalist (the default): everything but the password hash.
		$maximal = $this->requestExport($surfer);
		$this->assertIsExport($maximal);

		$this->assertStringContainsString($seed, $maximal->body, 'Maximalist export: the API token seed is missing.');
		$this->assertStringContainsString($activation, $maximal->body, 'Maximalist export: the activation token is missing.');
		$this->assertStringNotContainsString($hash, $maximal->body, 'The export contains the password hash.');

		if ($dlid !== null)
		{
			$this->assertStringContainsString($dlid, $maximal->body, 'Maximalist export: the Download ID is missing.');
		}

		$remembered = str_contains($maximal->body, $data['keySeries']);

		// Minimal.
		static::$fixtures->setComponentParams(['maximalist_export' => 0]);

		$minimal = $this->requestExport($surfer);
		$xml     = $this->assertIsExport($minimal);

		$this->assertStringNotContainsString($data['keySeries'], $minimal->body, 'The remember-me series was exported.');
		$this->assertSame([], $this->columnValues($xml, 'user_keys', 'token'), 'The remember-me token was exported.');
		$this->assertStringNotContainsString($seed, $minimal->body, 'The API token seed was exported.');
		$this->assertSame([], $this->columnValues($xml, 'users', 'otpKey'), 'The legacy TFA key was exported.');
		$this->assertSame([], $this->columnValues($xml, 'users', 'otep'), 'The legacy TFA backup codes were exported.');
		$this->assertStringNotContainsString($hash, $minimal->body, 'The export contains the password hash.');

		// Personal data is still there: this is redaction of credentials, not of the export.
		$this->assertStringContainsString($username . '@example.test', $minimal->body, 'The email address is missing.');
		$this->assertStringContainsString('E2E-CITY-' . $userId, $minimal->body, 'The profile data is missing.');

		if ($dlid !== null)
		{
			$this->assertStringNotContainsString($dlid, $minimal->body, 'The full Download ID was exported.');
			$this->assertStringContainsString(substr($dlid, -4), $minimal->body, 'The masked Download ID lost its last four characters.');
		}

		// Last, because they skip.
		$this->assertOrKnownIssues([
			7 => [
				!str_contains($minimal->body, $activation),
				'With Maximalist Export off, the account activation / password reset token is still exported: Data Compliance drops it from its own "users" domain, but the "users" domain of core plg_privacy_user carries it too, and Export::mapJoomlaPrivacyExportDomain() only filters joomlatoken.token.',
			],
			5 => [
				$remembered,
				'The user\'s remember-me keys (#__user_keys) are never exported: plg_datacompliance_joomla looks them up with user_id = <numeric id>, but that column holds the USERNAME.',
			],
		]);
	}

	/**
	 * The export of a user id that does not exist is refused cleanly: no crash, and no export trail
	 * for an account that is not there.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testExportOfMissingUser(): void
	{
		$missing  = (int) $this->db()->value('SELECT MAX(id) FROM #__users') + 1000;
		$response = $this->requestExport($this->superUserFrontend(), $missing);

		$this->assertStringNotContainsString('<domain', $response->body, 'An export was produced for a user who does not exist.');

		$this->assertTrue(
			$response->code < 500 && $this->exportTrailCount($missing) === 0,
			sprintf(
				'Exporting a user id that does not exist is an HTTP %d, and leaves %d export audit trail row(s) for it: ExportModel::exportSimpleXML() records the trail before plg_datacompliance_joomla throws "Cannot find a user record", and nothing catches that exception.',
				$response->code,
				$this->exportTrailCount($missing)
			)
		);
	}

	/**
	 * The export includes the ATS tickets the user filed, with every post (M4 is by design: the whole
	 * ticket, staff replies included), and the attachment metadata.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testExportIncludesAtsTickets(): void
	{
		$this->requireSibling('ats');

		$response = $this->requestExport($this->loggedIn('alice'));
		$xml      = $this->assertIsExport($response);

		$this->assertOrKnownIssue(
			in_array('ats_tickets', $this->domainNames($xml), true),
			6,
			'The export contains no ATS data at all on ATS 5+: plg_datacompliance_ats only calls setEventResult() inside the try block for #__ats_users_usertags, a table ATS 5 no longer has, so the exception is swallowed and the whole ATS export is discarded.'
		);

		foreach (['ats_tickets', 'ats_posts', 'ats_attachments'] as $domain)
		{
			$this->assertContains($domain, $this->domainNames($xml), sprintf('The export has no %s domain.', $domain));
		}

		foreach (['E2E private ticket of alice', 'E2E public ticket of alice', 'E2E-ATS-USER-POST-alice', 'E2E-ATS-STAFF-REPLY-alice', 'e2e-alice.txt'] as $needle)
		{
			$this->assertStringContainsString(htmlspecialchars($needle), $response->body, sprintf('"%s" is missing from the export.', $needle));
		}

		// Other people's tickets are not the user's data.
		$this->assertStringNotContainsString('E2E-ATS-BOB-POST-alice', $response->body, 'Another user\'s ticket was exported.');
	}

	/**
	 * The export includes the ARS download log and Download IDs.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testExportIncludesArsRecords(): void
	{
		$this->requireSibling('ars');

		$ars      = static::$fixtures->getManifest()['ars']['alice'];
		$response = $this->requestExport($this->loggedIn('alice'));
		$xml      = $this->assertIsExport($response);

		$this->assertContains('ars_log', $this->domainNames($xml));
		$this->assertStringContainsString('192.0.2.44', $response->body, 'The ARS download log entry is missing.');
		$this->assertStringContainsString($ars['dlidValue'], $response->body, 'The Download ID is missing.');
	}

	/**
	 * Assert that a response is a personal data export, and parse it.
	 *
	 * @param   Response  $response  The response.
	 *
	 * @return  SimpleXMLElement
	 * @since   4.1.0
	 */
	private function assertIsExport(Response $response): SimpleXMLElement
	{
		$this->assertStatus(200, $response, 'The export was not served.');
		$this->assertStringContainsString('xml', strtolower((string) $response->getHeader('content-type')), $response->summary());

		$previous = libxml_use_internal_errors(true);
		$xml      = simplexml_load_string($response->body);
		libxml_use_internal_errors($previous);

		$this->assertInstanceOf(SimpleXMLElement::class, $xml, "The export is not well-formed XML.\n" . $response->summary());
		$this->assertSame('root', $xml->getName());

		return $xml;
	}

	/**
	 * The names of the export's domains.
	 *
	 * @param   SimpleXMLElement  $xml  The export.
	 *
	 * @return  string[]
	 * @since   4.1.0
	 */
	private function domainNames(SimpleXMLElement $xml): array
	{
		$names = [];

		foreach ($xml->xpath('/root/domain') as $domain)
		{
			$names[] = (string) $domain['name'];
		}

		return $names;
	}

	/**
	 * The values of a named column across every item of a domain.
	 *
	 * @param   SimpleXMLElement  $xml     The export.
	 * @param   string            $domain  The domain name.
	 * @param   string            $column  The column name.
	 *
	 * @return  string[]
	 * @since   4.1.0
	 */
	private function columnValues(SimpleXMLElement $xml, string $domain, string $column): array
	{
		return array_map(
			'strval',
			$xml->xpath(sprintf('/root/domain[@name="%s"]/item/column[@name="%s"]', $domain, $column)) ?: []
		);
	}
}
