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
use Akeeba\DataCompliance\IntegrationTest\Engine\Surfer;

/**
 * The user changes audit trail recorded by plg_user_datacompliance.
 *
 * Every change goes through Joomla's own forms — the front-end profile editor, the back-end user
 * editor, the password reset request — because what the plugin sees in onUserBeforeSave depends on
 * exactly what those forms post.
 *
 * Regression tests for H3 (the Joomla API token logged in plaintext), L1 (the activation token logged),
 * I18 (profile and custom field changes never logged), L20 (the "wiping" session flag left on) and
 * the User Trails view's escaping (M3).
 *
 * @since 4.1.0
 */
class UserTrailTest extends AbstractE2ETestCase
{
	/**
	 * A front-end profile edit is recorded: what changed, from what to what, and by whom.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testProfileEditIsRecorded(): void
	{
		$userId = static::$fixtures->createUser(['name' => 'Before Name']);
		$surfer = $this->loginAs($userId);

		$this->saveFrontendProfile($surfer, ['jform[name]' => 'After Name']);

		$trail = $this->latestTrail($userId);

		$this->assertNotNull($trail, 'The profile edit was not recorded.');
		$this->assertSame($userId, (int) $trail['created_by'], 'The trail does not record who made the change.');
		$this->assertSame(['from' => 'Before Name', 'to' => 'After Name'], $trail['items']['name'] ?? null);
		$this->assertArrayNotHasKey('password', $trail['items'], 'An unchanged password was recorded as changed.');
	}

	/**
	 * I18 and the custom field / parameter bookkeeping: changing a user parameter and a custom field in
	 * the same save records both.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testParameterAndCustomFieldChangesAreBothRecorded(): void
	{
		$userId = static::$fixtures->createUser();
		$surfer = $this->loginAs($userId);
		$field  = static::$fixtures->getManifest()['fieldName'];

		// The control: a parameter change on its own IS recorded, so what goes missing below is lost to
		// the custom field change, not to the parameter bookkeeping.
		$this->saveFrontendProfile($surfer, ['jform[params][timezone]' => 'Europe/Berlin']);
		$this->assertStringContainsString('Europe\/Berlin', json_encode($this->latestTrail($userId)['items'] ?? []), 'A parameter change alone was not recorded.');

		$this->saveFrontendProfile($surfer, [
			'jform[params][timezone]'           => 'Europe/Athens',
			'jform[com_fields][' . $field . ']' => 'E2E-NEW-PHONE',
		]);

		$trail = $this->latestTrail($userId);

		$this->assertNotNull($trail, 'The profile edit was not recorded.');

		$json   = json_encode($trail['items']);
		$params = str_contains($json, 'Europe\/Athens');
		$custom = str_contains($json, 'E2E-NEW-PHONE');

		$this->assertTrue(
			$params && $custom,
			sprintf(
				'Changing a user parameter and a custom field in one save does not record both (parameter recorded: %s; custom field recorded: %s). getCustomFieldsChanges() writes its result to $changes[\'change_params\'] — the key the user parameter changes use — overwriting them.',
				$params ? 'yes' : 'NO',
				$custom ? 'yes' : 'NO'
			)
		);
	}

	/**
	 * H3: a Super User saving their own account in the back-end — where plg_user_token posts the
	 * ready-to-use API token back with the form — must not leave the token, or its seed, in the trail.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testApiTokenIsNeverRecorded(): void
	{
		$adminId = static::$fixtures->userId('admin');
		$seed    = (string) $this->db()->value(
			"SELECT profile_value FROM #__user_profiles WHERE user_id = ? AND profile_key = 'joomlatoken.token'",
			[$adminId]
		);
		$super   = $this->superUser();
		$form    = $this->backendUserForm($super, $adminId);
		$fields  = $this->formFields($form, 'user-form');
		$token   = $fields['jform[joomlatoken][token]'] ?? '';

		$this->assertNotSame('', $token, 'The back-end user form shows no API token; the test would prove nothing.');

		$fields['jform[name]'] = 'Test Super User E2E';
		$fields['task']        = 'user.apply';

		$response = $super->post($this->formAction($form, 'user-form'), $fields);

		$this->assertNotServerError($response);

		try
		{
			$trail = $this->latestTrail($adminId);

			$this->assertNotNull($trail, 'The Super User\'s profile edit was not recorded.');
			$this->assertSame('Test Super User E2E', $trail['items']['name']['to'] ?? null);

			$raw = (string) $this->db()->value(
				'SELECT items FROM #__datacompliance_usertrails WHERE datacompliance_usertrail_id = ?',
				[$trail['datacompliance_usertrail_id']]
			);

			$this->assertStringNotContainsString($token, $raw, 'The API token is in the user trail.');

			if ($seed !== '')
			{
				$this->assertStringNotContainsString($seed, $raw, 'The API token seed is in the user trail.');
			}
		}
		finally
		{
			$this->db()->query("UPDATE #__users SET name = 'Test Super User' WHERE id = ?", [$adminId]);
		}
	}

	/**
	 * L1: a password reset request stores an activation token on the account; the trail records THAT
	 * one was set, never the token itself — neither the hash in the database nor the one in the email.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testActivationTokenIsNeverRecorded(): void
	{
		$userId = static::$fixtures->createUser();
		$email  = $this->userRow($userId)['email'];
		$guest  = $this->guest();

		$this->mailpit()->clear();

		$page     = $guest->get('index.php', ['option' => 'com_users', 'view' => 'reset']);
		$fields   = $this->formFields($page, 'user-registration');
		$fields['jform[email]'] = $email;
		$fields['task']         = 'reset.request';

		$response = $guest->post($this->formAction($page, 'user-registration'), $fields);

		$this->assertNotServerError($response);

		$activation = (string) $this->userRow($userId)['activation'];

		$this->assertNotSame('', $activation, 'The reset request did not set an activation token; the test would prove nothing.');

		$trail = $this->latestTrail($userId);

		$this->assertNotNull($trail, 'The activation change was not recorded at all.');
		$this->assertArrayHasKey('activation', $trail['items']);

		$raw = (string) $this->db()->value(
			'SELECT items FROM #__datacompliance_usertrails WHERE datacompliance_usertrail_id = ?',
			[$trail['datacompliance_usertrail_id']]
		);

		$this->assertStringNotContainsString($activation, $raw, 'The activation token hash is in the user trail.');

		foreach ($this->mailpit()->messagesTo($email) as $summary)
		{
			$message = $this->mailpit()->message($summary['ID']);

			if (preg_match('/token=([0-9a-f]{32})/i', $message['Text'], $match))
			{
				$this->assertStringNotContainsString($match[1], $raw, 'The emailed reset token is in the user trail.');
			}
		}
	}

	/**
	 * L20: after wiping someone else, the actor's own changes are still recorded — the "wiping"
	 * session flag that suppresses the trail during a wipe is cleared afterwards.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testTrailResumesAfterWipingSomeoneElse(): void
	{
		static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 0]);

		try
		{
			$wiperId  = static::$fixtures->createUser([
				'groups' => [static::$fixtures->groupId('registered'), static::$fixtures->groupId('wipers')],
				'name'   => 'Wiper Before',
			]);
			$victimId = static::$fixtures->createUser();
			$wiper    = $this->loginAs($wiperId);

			$this->requestWipe($wiper, $victimId);

			$this->assertCount(1, $this->wipeTrails($victimId), 'The wipe did not happen.');
			$this->assertNull($this->latestTrail($victimId), 'The wipe itself was recorded as a profile change (with the personal data it removed).');

			$this->saveFrontendProfile($wiper, ['jform[name]' => 'Wiper After']);

			$trail = $this->latestTrail($wiperId);

			$this->assertNotNull($trail, 'The trail stayed switched off for the rest of the session after a wipe.');
			$this->assertSame('Wiper After', $trail['items']['name']['to'] ?? null);
		}
		finally
		{
			static::$fixtures->setPluginParams('datacompliance', 'email', ['admins' => 1]);
		}
	}

	/**
	 * M3: array values in the User Trails view are escaped. Stored markup in a trail must reach a
	 * Super User's browser as text.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testUserTrailsViewEscapesStoredMarkup(): void
	{
		$aliceId = static::$fixtures->userId('alice');
		$marker  = '<img src=x onerror=alert(' . random_int(1000, 9999) . ')>';

		$this->db()->insert('#__datacompliance_usertrails', [
			'user_id'      => $aliceId,
			'created_on'   => gmdate('Y-m-d H:i:s'),
			'created_by'   => $aliceId,
			'requester_ip' => '192.0.2.10',
			'items'        => json_encode([
				'name'          => ['from' => 'Plain ' . $marker, 'to' => 'Alice Example'],
				'change_params' => ['from' => ['editor' => $marker], 'to' => ['editor' => 'none']],
			]),
		]);

		try
		{
			$page = $this->superUser()->get($this->adminUrl(['view' => 'usertrails']));

			$this->assertStatus(200, $page);
			$this->assertBodyNotContains($marker, $page, 'Stored markup is rendered raw in the User Trails view.');
			$this->assertBodyContains(htmlspecialchars('onerror=alert', ENT_QUOTES), $page, 'The trail entry is not shown at all; the test would prove nothing.');
		}
		finally
		{
			$this->db()->query("DELETE FROM #__datacompliance_usertrails WHERE requester_ip = '192.0.2.10'");
		}
	}

	/**
	 * Log a throwaway account into the front-end.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  Surfer
	 * @since   4.1.0
	 */
	private function loginAs(int $userId): Surfer
	{
		return $this->frontendLogin((string) $this->userRow($userId)['username']);
	}

	/**
	 * Save the front-end profile form with some fields changed, as a browser would.
	 *
	 * @param   Surfer  $surfer   The logged-in user.
	 * @param   array   $changes  Form field name => new value.
	 *
	 * @return  Response
	 * @since   4.1.0
	 */
	private function saveFrontendProfile(Surfer $surfer, array $changes): Response
	{
		$page   = $surfer->get('index.php', ['option' => 'com_users', 'view' => 'profile', 'layout' => 'edit']);
		$fields = $this->formFields($page, 'member-profile');

		$this->assertNotEmpty($fields, "The profile edit form did not render.\n" . $page->summary());

		$fields         = array_merge($fields, $changes);
		$fields['task'] = 'profile.save';

		$response = $surfer->post($this->formAction($page, 'member-profile'), $fields);

		$this->assertNotServerError($response, 'Saving the profile');

		return $response;
	}

	/**
	 * Open the back-end user editor (which checks the record out) and return the form page.
	 *
	 * @param   Surfer  $super   A back-end Super User.
	 * @param   int     $userId  The account to edit.
	 *
	 * @return  Response
	 * @since   4.1.0
	 */
	private function backendUserForm(Surfer $super, int $userId): Response
	{
		$super->followRedirects = true;

		try
		{
			$response = $super->get('administrator/index.php', ['option' => 'com_users', 'task' => 'user.edit', 'id' => $userId]);
		}
		finally
		{
			$super->followRedirects = false;
		}

		$this->assertStatus(200, $response);

		return $response;
	}

	/**
	 * The action URL of a form, HTML entities decoded.
	 *
	 * @param   Response  $page    The page.
	 * @param   string    $formId  The form's id.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private function formAction(Response $page, string $formId): string
	{
		$this->assertMatchesRegularExpression(
			'/<form\b[^>]*\bid="' . preg_quote($formId, '/') . '"/',
			$page->body,
			sprintf('Form #%s is not on the page.', $formId)
		);

		preg_match('/<form\b(?=[^>]*\bid="' . preg_quote($formId, '/') . '")[^>]*\baction="([^"]*)"/', $page->body, $match);

		$action = html_entity_decode($match[1] ?? 'index.php', ENT_QUOTES);

		// Joomla renders root-relative actions; the surfer wants site-relative or absolute URLs.
		return preg_match('#^https?://#', $action) ? $action : ltrim($action, '/');
	}

	/**
	 * The newest user trail row of an account, with its items decoded.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  array|null
	 * @since   4.1.0
	 */
	private function latestTrail(int $userId): ?array
	{
		$row = $this->db()->row(
			'SELECT * FROM #__datacompliance_usertrails WHERE user_id = ? ORDER BY datacompliance_usertrail_id DESC LIMIT 1',
			[$userId]
		);

		if ($row === null)
		{
			return null;
		}

		$row['items'] = json_decode((string) $row['items'], true) ?: [];

		return $row;
	}
}
