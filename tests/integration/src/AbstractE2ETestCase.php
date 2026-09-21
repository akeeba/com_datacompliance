<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\IntegrationTest;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\IntegrationTest\Engine\Configuration;
use Akeeba\DataCompliance\IntegrationTest\Engine\ContainerCli;
use Akeeba\DataCompliance\IntegrationTest\Engine\Database;
use Akeeba\DataCompliance\IntegrationTest\Engine\JoomlaSession;
use Akeeba\DataCompliance\IntegrationTest\Engine\Mailpit;
use Akeeba\DataCompliance\IntegrationTest\Engine\Response;
use Akeeba\DataCompliance\IntegrationTest\Engine\Surfer;
use PHPUnit\Framework\TestCase;

/**
 * Base class for the end-to-end tests.
 *
 * Every test here drives a real HTTP request (or a real `cli/joomla.php` command) against a real
 * Joomla site with a real session. The assertions that matter most are the negative ones — the
 * export that must not happen, the account that must not be wiped — and for those the rule is:
 * assert the refusal AND the absence of its effect.
 *
 * @since 4.1.0
 */
abstract class AbstractE2ETestCase extends TestCase
{
	/**
	 * The confirmation phrase of the wipe form, from the shipped en-GB language file.
	 *
	 * @since 4.1.0
	 */
	protected const WIPE_PHRASE = 'I UNDERSTAND';

	/**
	 * The suite configuration.
	 *
	 * @var   Configuration
	 * @since 4.1.0
	 */
	protected static Configuration $config;

	/**
	 * The fixtures.
	 *
	 * @var   SiteProvisioner
	 * @since 4.1.0
	 */
	protected static SiteProvisioner $fixtures;

	/**
	 * Surfers created during a test, keyed by role, so each test starts from a clean session.
	 *
	 * @var   array<string, Surfer>
	 * @since 4.1.0
	 */
	private array $surfers = [];

	/**
	 * The login helper.
	 *
	 * @var   JoomlaSession
	 * @since 4.1.0
	 */
	protected JoomlaSession $session;

	/**
	 * Byte offset into php-errors.log when the test started.
	 *
	 * @var   int
	 * @since 4.1.0
	 */
	private int $phpErrorLogOffset = 0;

	/**
	 * Set up the shared configuration and fixtures.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		static::$config   = Configuration::getInstance();
		static::$fixtures = SiteProvisioner::getInstance();
	}

	/**
	 * Set up a test.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function setUp(): void
	{
		parent::setUp();

		$this->session           = new JoomlaSession();
		$this->phpErrorLogOffset = $this->phpErrorLogSize();
	}

	/**
	 * Tear down a test.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function tearDown(): void
	{
		$this->surfers = [];

		parent::tearDown();
	}

	/**
	 * Put the fixtures back the way they started.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function resetFixtures(): void
	{
		static::$fixtures->reset();
	}

	// -----------------------------------------------------------------------
	// Actors.
	// -----------------------------------------------------------------------

	/**
	 * A logged-out surfer.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	protected function guest(): Surfer
	{
		return $this->surfers['__guest'] ??= new Surfer(static::$config->getSiteUrl());
	}

	/**
	 * A surfer logged into the front-end as one of the shared accounts.
	 *
	 * @param   string  $role  A role, e.g. 'alice', 'exporter', 'wiper'.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	protected function loggedIn(string $role): Surfer
	{
		return $this->surfers[$role] ??= $this->frontendLogin(static::$fixtures->username($role));
	}

	/**
	 * A NEW surfer logged into the front-end with an arbitrary username, e.g. a throwaway account.
	 *
	 * @param   string       $username  The username.
	 * @param   string|null  $password  The password; the shared test password by default.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	protected function frontendLogin(string $username, ?string $password = null): Surfer
	{
		$surfer = new Surfer(static::$config->getSiteUrl());

		$this->session->loginFrontend($surfer, $username, $password ?? static::$config->getUserPassword());

		return $surfer;
	}

	/**
	 * A surfer logged into the back-end as the Super User.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	protected function superUser(): Surfer
	{
		if (isset($this->surfers['__super']))
		{
			return $this->surfers['__super'];
		}

		[$username, $password] = static::$config->getAdminCredentials();

		$surfer = new Surfer(static::$config->getSiteUrl());
		$this->session->loginBackend($surfer, $username, $password);

		return $this->surfers['__super'] = $surfer;
	}

	/**
	 * A surfer logged into the front-end as the Super User.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	protected function superUserFrontend(): Surfer
	{
		[$username, $password] = static::$config->getAdminCredentials();

		return $this->surfers['__superFrontend'] ??= $this->frontendLogin($username, $password);
	}

	/**
	 * A surfer logged into the back-end as one of the shared accounts. Only accounts with
	 * core.login.admin can do this: administrator, nomanage, super2.
	 *
	 * @param   string  $role  A role.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	protected function loggedInBackend(string $role): Surfer
	{
		$key = '__admin_' . $role;

		if (isset($this->surfers[$key]))
		{
			return $this->surfers[$key];
		}

		$surfer = new Surfer(static::$config->getSiteUrl());

		$this->session->loginBackend($surfer, static::$fixtures->username($role), static::$config->getUserPassword());

		return $this->surfers[$key] = $surfer;
	}

	// -----------------------------------------------------------------------
	// Access to the stack.
	// -----------------------------------------------------------------------

	/**
	 * The site's database.
	 *
	 * @return  Database
	 * @since   4.1.0
	 */
	protected function db(): Database
	{
		static $db = null;

		return $db ??= new Database(static::$config);
	}

	/**
	 * The outbound mail sink.
	 *
	 * @return  Mailpit
	 * @since   4.1.0
	 */
	protected function mailpit(): Mailpit
	{
		static $mailpit = null;

		return $mailpit ??= new Mailpit(static::$config->getMailpitUrl());
	}

	/**
	 * Joomla's console application, and the MinIO client, inside the stack.
	 *
	 * @return  ContainerCli
	 * @since   4.1.0
	 */
	protected function cli(): ContainerCli
	{
		static $cli = null;

		return $cli ??= new ContainerCli(static::$config);
	}

	/**
	 * Skip unless a sibling extension (ats, ars) is installed.
	 *
	 * @param   string  $name  'ats' or 'ars'.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function requireSibling(string $name): void
	{
		$manifest = static::$fixtures->getManifest();
		$key      = 'has' . ucfirst($name);

		if (empty($manifest[$key]))
		{
			$this->markTestSkipped(sprintf('%s is not installed on this site (WITH_%s=0, or --no-siblings).', strtoupper($name), strtoupper($name)));
		}
	}

	// -----------------------------------------------------------------------
	// URLs and requests.
	// -----------------------------------------------------------------------

	/**
	 * Build a front-end Data Compliance URL.
	 *
	 * @param   array  $query  Query parameters, merged over option=com_datacompliance.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	protected function siteUrl(array $query = []): string
	{
		return 'index.php?' . http_build_query(array_merge(['option' => 'com_datacompliance'], $query));
	}

	/**
	 * Build a back-end Data Compliance URL.
	 *
	 * @param   array  $query  Query parameters, merged over option=com_datacompliance.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	protected function adminUrl(array $query = []): string
	{
		return 'administrator/index.php?' . http_build_query(array_merge(['option' => 'com_datacompliance'], $query));
	}

	/**
	 * The anti-CSRF token of a logged-in surfer's session, read from a page that renders one.
	 *
	 * @param   Surfer  $surfer   The surfer.
	 * @param   bool    $backend  Read it from the back-end (a separate session) instead.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	protected function tokenFor(Surfer $surfer, bool $backend = false): string
	{
		return $backend
			? $surfer->fetchToken('administrator/index.php')
			// The Options page: every logged-in user may see it, and it is exempt from the consent
			// redirect, which would otherwise hijack any other page for an unconsented user.
			: $surfer->fetchToken($this->siteUrl(['view' => 'options']));
	}

	/**
	 * POST a personal data export request, exactly as the Options page's Export form does.
	 *
	 * @param   Surfer    $surfer   The surfer.
	 * @param   int|null  $userId   The account to export; null for "myself" (no user_id sent).
	 * @param   bool      $backend  Use the back-end Options page instead of the front-end one.
	 * @param   ?string   $token    The token to send; null for the session's real one, '' for none.
	 *
	 * @return  Response
	 * @since   4.1.0
	 */
	protected function requestExport(Surfer $surfer, ?int $userId = null, bool $backend = false, ?string $token = null): Response
	{
		$token ??= $this->tokenFor($surfer, $backend);
		$url     = $backend
			? $this->adminUrl(['view' => 'options', 'task' => 'export', 'format' => 'raw'])
			: $this->siteUrl(['view' => 'options', 'task' => 'export', 'format' => 'raw']);
		$data    = $userId === null ? [] : ['user_id' => $userId];

		if ($token !== '')
		{
			$data[$token] = 1;
		}

		return $surfer->post($url, $data);
	}

	/**
	 * POST the wipe confirmation form, exactly as the wipe page does.
	 *
	 * @param   Surfer    $surfer   The surfer.
	 * @param   int|null  $userId   The account to wipe; null for "myself".
	 * @param   string    $phrase   The confirmation phrase typed in.
	 * @param   bool      $backend  Use the back-end instead of the front-end.
	 * @param   ?string   $token    The token to send; null for the session's real one, '' for none.
	 *
	 * @return  Response
	 * @since   4.1.0
	 */
	protected function requestWipe(
		Surfer $surfer, ?int $userId = null, string $phrase = self::WIPE_PHRASE, bool $backend = false, ?string $token = null
	): Response
	{
		$token ??= $this->tokenFor($surfer, $backend);
		$url     = $backend
			? $this->adminUrl(['view' => 'options', 'task' => 'wipe'])
			: $this->siteUrl(['view' => 'options', 'task' => 'wipe']);
		$data    = ['phrase' => $phrase];

		if ($userId !== null)
		{
			$data['user_id'] = $userId;
		}

		if ($token !== '')
		{
			$data[$token] = 1;
		}

		return $surfer->post($url, $data);
	}

	/**
	 * POST the consent form, exactly as the Options page does.
	 *
	 * @param   Surfer       $surfer   The surfer.
	 * @param   bool         $enabled  Consent (true) or decline (false).
	 * @param   int|null     $userId   Record on behalf of this account; null for "myself".
	 * @param   string|null  $reason   The evidence of consent (on-behalf only).
	 * @param   ?string      $token    The token to send; null for the session's real one, '' for none.
	 *
	 * @return  Response
	 * @since   4.1.0
	 */
	protected function requestConsent(Surfer $surfer, bool $enabled, ?int $userId = null, ?string $reason = null, ?string $token = null): Response
	{
		$token ??= $this->tokenFor($surfer);
		$query   = ['view' => 'options', 'task' => 'consent'];

		if ($userId !== null)
		{
			$query['user_id'] = $userId;
		}

		$data = ['enabled' => $enabled ? 1 : 0];

		if ($reason !== null)
		{
			$data['reason'] = $reason;
		}

		if ($token !== '')
		{
			$data[$token] = 1;
		}

		return $surfer->post($this->siteUrl($query), $data);
	}

	/**
	 * The name/value pairs a browser would submit for a form on the page.
	 *
	 * Disabled controls, unchecked checkboxes and radios, buttons and file inputs are left out, as a
	 * browser would. For a select, the selected option (or the first one).
	 *
	 * @param   Response  $response  The page.
	 * @param   string    $formId    The form's id attribute.
	 *
	 * @return  array<string, string>
	 * @since   4.1.0
	 */
	protected function formFields(Response $response, string $formId): array
	{
		$dom = new \DOMDocument();

		if (!@$dom->loadHTML($response->body))
		{
			return [];
		}

		$xpath  = new \DOMXPath($dom);
		$fields = [];
		$query  = sprintf(
			'//form[@id="%1$s"]//*[self::input or self::select or self::textarea][@name][ancestor::form[1][@id="%1$s"]]',
			$formId
		);

		/** @var \DOMElement $element */
		foreach ($xpath->query($query) as $element)
		{
			$name = $element->getAttribute('name');

			if ($element->hasAttribute('disabled'))
			{
				continue;
			}

			switch (strtolower($element->tagName))
			{
				case 'select':
					$selected = $xpath->query('.//option[@selected]', $element);
					$option   = $selected->length ? $selected->item(0) : $xpath->query('.//option', $element)->item(0);

					$fields[$name] = $option instanceof \DOMElement ? $option->getAttribute('value') : '';
					break;

				case 'textarea':
					$fields[$name] = $element->textContent;
					break;

				default:
					$type = strtolower($element->getAttribute('type'));

					if (in_array($type, ['checkbox', 'radio'], true) && !$element->hasAttribute('checked'))
					{
						break;
					}

					if (in_array($type, ['submit', 'button', 'file', 'image', 'reset'], true))
					{
						break;
					}

					$fields[$name] = $element->getAttribute('value');
			}
		}

		return $fields;
	}

	// -----------------------------------------------------------------------
	// Observations.
	// -----------------------------------------------------------------------

	/**
	 * A user row, or null when there is none.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  array|null
	 * @since   4.1.0
	 */
	protected function userRow(int $userId): ?array
	{
		return $this->db()->row('SELECT * FROM #__users WHERE id = ?', [$userId]);
	}

	/**
	 * The wipe audit trail rows of an account.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  array[]
	 * @since   4.1.0
	 */
	protected function wipeTrails(int $userId): array
	{
		return $this->db()->all('SELECT * FROM #__datacompliance_wipetrails WHERE user_id = ?', [$userId]);
	}

	/**
	 * How many export audit trail rows an account has.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  int
	 * @since   4.1.0
	 */
	protected function exportTrailCount(int $userId): int
	{
		return (int) $this->db()->value('SELECT COUNT(*) FROM #__datacompliance_exporttrails WHERE user_id = ?', [$userId]);
	}

	/**
	 * The consent record of an account, or null.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  array|null
	 * @since   4.1.0
	 */
	protected function consentRow(int $userId): ?array
	{
		return $this->db()->row('SELECT * FROM #__datacompliance_consenttrails WHERE created_by = ?', [$userId]);
	}

	/**
	 * Did the account survive untouched? Compares the columns a wipe rewrites.
	 *
	 * @param   array   $before   The user row before the attempt.
	 * @param   string  $message  Context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertUserUntouched(array $before, string $message = ''): void
	{
		$after = $this->userRow((int) $before['id']);

		$this->assertNotNull($after, trim('The account no longer exists. ' . $message));

		foreach (['name', 'username', 'email', 'password', 'registerDate', 'block'] as $column)
		{
			$this->assertSame(
				(string) $before[$column],
				(string) $after[$column],
				trim(sprintf('Column "%s" of user #%d changed. %s', $column, $before['id'], $message))
			);
		}

		$this->assertSame([], $this->wipeTrails((int) $before['id']), trim('A wipe audit trail was recorded. ' . $message));
	}

	/**
	 * PHP errors, warnings and notices the site logged since this test started.
	 *
	 * @return  string[]
	 * @since   4.1.0
	 */
	protected function newPhpErrors(): array
	{
		$file = static::$config->getSiteRoot() . '/php-errors.log';

		clearstatcache(true, $file);

		if (!is_file($file) || filesize($file) <= $this->phpErrorLogOffset)
		{
			return [];
		}

		$handle = fopen($file, 'r');
		fseek($handle, $this->phpErrorLogOffset);
		$contents = stream_get_contents($handle);
		fclose($handle);

		return array_values(array_filter(array_map('trim', explode("\n", (string) $contents))));
	}

	/**
	 * The size of php-errors.log right now.
	 *
	 * @return  int
	 * @since   4.1.0
	 */
	private function phpErrorLogSize(): int
	{
		$file = static::$config->getSiteRoot() . '/php-errors.log';

		clearstatcache(true, $file);

		return is_file($file) ? (int) filesize($file) : 0;
	}

	// -----------------------------------------------------------------------
	// Assertions.
	//
	// Each carries the response summary into the failure message. A bare
	// "failed asserting that 200 matches 403" tells you nothing about which
	// of the moving parts (session, ACL fixture, route, controller) broke.
	// -----------------------------------------------------------------------

	/**
	 * Assert that the response has a given status code.
	 *
	 * @param   int       $expected  The expected status code.
	 * @param   Response  $response  The response.
	 * @param   string    $message   Extra context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertStatus(int $expected, Response $response, string $message = ''): void
	{
		$this->assertSame($expected, $response->code, trim($message . "\n" . $response->summary()));
	}

	/**
	 * Assert that the request was refused.
	 *
	 * Joomla refuses in more than one shape and all of them count:
	 *
	 *   - an error page — 401, 403 or 404 from an uncaught exception carrying that code;
	 *   - a redirect to the login page;
	 *   - a redirect carrying a warning or error message. BaseController::checkToken() does NOT throw
	 *     on a bad token: it enqueues JINVALID_TOKEN_NOTICE and redirects. The message lives in the
	 *     session of the surfer that made the request, so the redirect is followed WITH THAT SURFER —
	 *     a fresh one would find an empty queue and read the refusal as success.
	 *
	 * For a request that would change state, this assertion is necessary but not sufficient: also
	 * assert that the change did not happen.
	 *
	 * @param   Surfer    $surfer    The surfer that made the request, whose session holds any message.
	 * @param   Response  $response  The response.
	 * @param   string    $message   Extra context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertRefused(Surfer $surfer, Response $response, string $message = ''): void
	{
		$context = trim($message . "\n" . $response->summary());

		if (in_array($response->code, [401, 403, 404], true))
		{
			$this->assertTrue(true);

			return;
		}

		if ($response->isRedirect())
		{
			$location = (string) $response->getLocation();

			if (stripos($location, 'com_users') !== false && stripos($location, 'login') !== false)
			{
				$this->assertTrue(true);

				return;
			}

			// Follow the whole chain: for a user without consent, the landing page is itself redirected
			// to the consent page, which is where the queued message is finally rendered.
			$wasFollowing            = $surfer->followRedirects;
			$surfer->followRedirects = true;

			try
			{
				$landing = $surfer->get($location);
			}
			finally
			{
				$surfer->followRedirects = $wasFollowing;
			}

			$this->assertTrue(
				$this->bodyLooksLikeRefusal($landing->body),
				"The request redirected, but the page it redirected to carries no refusal message,\n"
				. "so this looks like the action succeeded.\n" . $context . "\nLanding page: " . $landing->summary()
			);

			return;
		}

		if ($response->code === 200 && $this->bodyLooksLikeRefusal($response->body))
		{
			$this->assertTrue(true);

			return;
		}

		$this->fail("Expected the request to be refused, but it was not.\n" . $context);
	}

	/**
	 * Assert that the response is not a server error, and that the site logged no PHP fatal error.
	 *
	 * A refusal delivered as an HTTP 500 — an uncaught TypeError, say — is not a refusal: it is a
	 * crash that happens to stop the action.
	 *
	 * @param   Response  $response  The response.
	 * @param   string    $message   Extra context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertNotServerError(Response $response, string $message = ''): void
	{
		$fatals = array_filter($this->newPhpErrors(), fn(string $line): bool => stripos($line, 'Fatal error') !== false);

		$this->assertLessThan(
			500,
			$response->code,
			trim($message . "\n" . $response->summary() . "\n" . implode("\n", $fatals))
		);
		$this->assertSame([], array_values($fatals), trim("The site logged a PHP fatal error.\n" . $message));
	}

	/**
	 * Assert that the response body contains a string.
	 *
	 * @param   string    $needle    The string to look for.
	 * @param   Response  $response  The response.
	 * @param   string    $message   Extra context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertBodyContains(string $needle, Response $response, string $message = ''): void
	{
		// assertStringContainsString() would print the entire rendered page as the haystack, which
		// for a Joomla back-end view is tens of kilobytes of inline JSON and buries the message.
		$this->assertTrue(
			str_contains($response->body, $needle),
			trim(sprintf('Expected the response body to contain "%s".', $needle) . "\n" . $message . "\n" . $response->summary())
		);
	}

	/**
	 * Assert that the response body does NOT contain a string.
	 *
	 * @param   string    $needle    The string that must be absent.
	 * @param   Response  $response  The response.
	 * @param   string    $message   Extra context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertBodyNotContains(string $needle, Response $response, string $message = ''): void
	{
		$this->assertFalse(
			str_contains($response->body, $needle),
			trim(sprintf('Expected the response body NOT to contain "%s".', $needle) . "\n" . $message . "\n" . $response->summary())
		);
	}

	/**
	 * Assert that no link or form action on the page carries an anti-CSRF token in its URL.
	 *
	 * The session token in a URL ends up in browser history, access logs and Referer headers (L2).
	 *
	 * @param   Response  $response  The page.
	 * @param   string    $message   Extra context.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertNoTokenInLinks(Response $response, string $message = ''): void
	{
		preg_match_all('/\b(?:href|action)\s*=\s*["\']([^"\']*)["\']/i', $response->body, $matches);

		$offending = array_filter(
			$matches[1],
			fn(string $url): bool => str_contains($url, 'com_datacompliance')
				&& (bool) preg_match('/[?&](?:amp;)?[0-9a-f]{32}=1\b/i', $url)
		);

		$this->assertSame([], array_values($offending), trim("A Data Compliance URL carries the anti-CSRF token.\n" . $message));
	}

	/**
	 * Assert something the product currently gets wrong, skipping with a pointer to known-issues.md
	 * instead of failing.
	 *
	 * The suite surfaces suspected bugs rather than encoding them: asserting today's buggy behaviour
	 * as correct would turn the test green for the wrong reason. When the condition holds (the bug is
	 * fixed), this is an ordinary passing assertion — at which point replace the call with a plain
	 * assertion so a regression fails loudly instead of skipping.
	 *
	 * @param   bool    $condition  The correct behaviour holds.
	 * @param   int     $issue      The item number in known-issues.md.
	 * @param   string  $diagnosis  What is wrong, in one or two sentences.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertOrKnownIssue(bool $condition, int $issue, string $diagnosis): void
	{
		if ($condition)
		{
			$this->assertTrue(true);

			return;
		}

		$this->markTestSkipped(sprintf('Known issue #%d (see known-issues.md): %s', $issue, $diagnosis));
	}

	/**
	 * Several {@see assertOrKnownIssue()} checks at once, so that one known issue does not hide the
	 * next: every failing one is listed in a single skip message.
	 *
	 * @param   array<int, array{0: bool, 1: string}>  $checks  Issue number => [correct behaviour holds, diagnosis].
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	protected function assertOrKnownIssues(array $checks): void
	{
		$failing = [];

		foreach ($checks as $issue => [$condition, $diagnosis])
		{
			if (!$condition)
			{
				$failing[] = sprintf('Known issue #%d (see known-issues.md): %s', $issue, $diagnosis);
			}
		}

		if ($failing === [])
		{
			$this->assertTrue(true);

			return;
		}

		$this->markTestSkipped(implode("\n", $failing));
	}

	/**
	 * Does this body look like Joomla saying no?
	 *
	 * Primarily this looks at the SEVERITY of the rendered system message, not at its wording.
	 * Joomla enqueues a refusal as 'warning' or 'error' and a success as 'info' or 'success', and the
	 * template renders that severity into the markup — `<div class="alert alert-warning">` in
	 * Cassiopeia, `<joomla-alert type="warning">` elsewhere, or the message queue JSON in the
	 * script options. The phrases below are a backstop for refusals rendered as a full error page.
	 *
	 * Note that the Options page itself renders `alert-warning` boxes of its own (the "this cannot be
	 * undone" notices), so on that page assert on content and state instead of calling this.
	 *
	 * @param   string  $body  The response body.
	 *
	 * @return  bool
	 * @since   4.1.0
	 */
	protected function bodyLooksLikeRefusal(string $body): bool
	{
		if (preg_match('/<joomla-alert\b[^>]*\btype\s*=\s*["\'](warning|danger|error)["\']/i', $body))
		{
			return true;
		}

		// Joomla renders the message queue into the script options as {"joomla.messages":[{"error":[…]}]}
		if (preg_match('/"joomla\.messages"\s*:\s*\[\s*\{\s*"(warning|error|danger)"/i', $body))
		{
			return true;
		}

		$needles = [
			// JINVALID_TOKEN_NOTICE
			'security token did not match',
			// JERROR_ALERTNOAUTHOR
			'not authorised to view this resource',
			'not authorized to view this resource',
			// Data Compliance's own refusals
			'cannot be deleted automatically',
			'HAS NOT been deleted',
			'was NOT deleted',
			'You must provide evidence of consent',
		];

		foreach ($needles as $needle)
		{
			if (stripos($body, $needle) !== false)
			{
				return true;
			}
		}

		return false;
	}
}
