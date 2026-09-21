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
 * Who may see which Options page, what it offers them, and how it renders user data.
 *
 * Regression tests for L16 (guests rendering the Options page), the "view another user" rule of
 * assertUserAccess('options') and its mirror in the view, L19 (usernames unescaped in the page) and
 * M2 (the shared user layout of the back-end lists printing user data unescaped).
 *
 * @since 4.1.0
 */
class OptionsPageTest extends AbstractE2ETestCase
{
	/**
	 * L16: a guest gets nothing from the Options page, whatever the task.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testGuestsAreRefused(): void
	{
		$guest = $this->guest();

		foreach ([[], ['task' => 'display'], ['task' => 'options'], ['task' => 'wipe'], ['task' => 'export', 'format' => 'raw']] as $query)
		{
			$response = $guest->get($this->siteUrl(['view' => 'options'] + $query));

			$this->assertRefused($guest, $response, 'guest: ' . json_encode($query));
			$this->assertBodyNotContains('E2E-PRIVACY-POLICY-INTRO', $response, 'A guest was shown the Options page.');
		}
	}

	/**
	 * A user without any Data Compliance privilege may not view someone else's Options page.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testPlainUserCannotViewSomeoneElse(): void
	{
		$alice    = $this->loggedIn('alice');
		$response = $alice->get($this->siteUrl(['view' => 'options', 'user_id' => static::$fixtures->userId('bob')]));

		$this->assertRefused($alice, $response);
		$this->assertBodyNotContains(static::$fixtures->email('bob'), $response, 'Bob\'s page was shown to Alice.');
		$this->assertBodyNotContains('Bob Example', $response, 'Bob\'s page was shown to Alice.');
	}

	/**
	 * Someone with `export` sees another user's page with the Export form for that user, and no Delete
	 * button; someone with `wipe`, the reverse.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testPrivilegedUsersAreOfferedWhatTheyMayDo(): void
	{
		$bobId = static::$fixtures->userId('bob');

		$page = $this->loggedIn('exporter')->get($this->siteUrl(['view' => 'options', 'user_id' => $bobId]));

		$this->assertStatus(200, $page);
		$this->assertTrue($this->offersExportOf($page->body, $bobId), 'The exporter is not offered the export of bob.');
		$this->assertDoesNotMatchRegularExpression('/task=wipe(&|&amp;)user_id=' . $bobId . '\b/', $page->body, 'The exporter is offered to delete bob.');

		$page = $this->loggedIn('wiper')->get($this->siteUrl(['view' => 'options', 'user_id' => $bobId]));

		$this->assertStatus(200, $page);
		$this->assertMatchesRegularExpression('/task=wipe(&|&amp;)user_id=' . $bobId . '\b/', $page->body, 'The wiper is not offered to delete bob.');
		$this->assertFalse($this->offersExportOf($page->body, $bobId), 'The wiper is offered the export of bob.');
		$this->assertNoTokenInLinks($page);
	}

	/**
	 * The component's showexport / showwipe options hide the controls on the user's own page.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testShowExportAndShowWipeOptions(): void
	{
		static::$fixtures->setComponentParams(['showexport' => 0, 'showwipe' => 0]);

		try
		{
			$page = $this->loggedIn('alice')->get($this->siteUrl(['view' => 'options']));

			$this->assertStatus(200, $page);
			$this->assertStringNotContainsString('task=export', html_entity_decode($page->body), 'The Export button is shown although showexport is off.');
			$this->assertStringNotContainsString('task=wipe', html_entity_decode($page->body), 'The Delete button is shown although showwipe is off.');
		}
		finally
		{
			static::$fixtures->setComponentParams(['showexport' => 1, 'showwipe' => 1]);
		}
	}

	/**
	 * L19: a username with markup (created outside Joomla's forms, e.g. by a bridge or an SSO plugin,
	 * which do not go through Table\User::check()) is escaped on the Options and wipe pages.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testUsernameIsEscapedOnTheOptionsPages(): void
	{
		$marker   = 'e2e<b>' . bin2hex(random_bytes(3)) . '</b>';
		$victimId = static::$fixtures->createUser(['username' => $marker, 'email' => 'e2e-markup-' . bin2hex(random_bytes(3)) . '@example.test']);
		$wiper    = $this->loggedIn('wiper');

		foreach ([['view' => 'options'], ['view' => 'options', 'task' => 'wipe']] as $query)
		{
			$page = $wiper->get($this->siteUrl($query + ['user_id' => $victimId]));

			$this->assertStatus(200, $page);
			$this->assertBodyNotContains($marker, $page, 'The username is raw HTML on ' . json_encode($query));
			$this->assertBodyContains(htmlspecialchars($marker), $page, 'The username is not shown at all on ' . json_encode($query));
		}
	}

	/**
	 * M2: the user summary the back-end lists share escapes name, username and email.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testBackendListsEscapeUserData(): void
	{
		$marker = '<img src=x onerror=alert(' . random_int(1000, 9999) . ')>';
		$userId = static::$fixtures->createUser(['name' => 'E2E ' . $marker]);

		// Give the account a row in each audit list.
		$this->db()->insert('#__datacompliance_exporttrails', [
			'user_id' => $userId, 'created_on' => gmdate('Y-m-d H:i:s'), 'created_by' => $userId, 'requester_ip' => '192.0.2.11',
		]);

		$super = $this->superUser();

		foreach (['consenttrails', 'exporttrails', 'lifecycle'] as $view)
		{
			$page = $super->get($this->adminUrl(['view' => $view, 'list[limit]' => 0]));

			$this->assertStatus(200, $page, $view);
			$this->assertBodyNotContains($marker, $page, sprintf('The user\'s name is raw HTML in the %s view.', $view));
			$this->assertBodyContains(htmlspecialchars('E2E ' . $marker, ENT_QUOTES), $page, sprintf('The user is not listed in the %s view; the test would prove nothing.', $view));
		}
	}

	/**
	 * Does the page carry an Export form for the given user?
	 *
	 * @param   string  $html    The page.
	 * @param   int     $userId  The user.
	 *
	 * @return  bool
	 * @since   4.1.0
	 */
	private function offersExportOf(string $html, int $userId): bool
	{
		preg_match_all('/<form\b[^>]*task=export[^>]*>.*?<\/form>/s', $html, $forms);

		foreach ($forms[0] as $form)
		{
			if (preg_match('/name="user_id"\s+value="' . $userId . '"/', $form))
			{
				return true;
			}
		}

		return false;
	}
}
