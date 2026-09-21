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
 * Back-end access control: the Dispatcher's core.manage gate, and the controllers behind it.
 *
 * Regression tests for H1 (`view=options` exempted every controller from the core.manage check) and
 * the defence-in-depth checks added with it, and for the browse-only trail and lifecycle views.
 *
 * The actor is `nomanage`: an Administrator — back-end login, and core.manage on every other
 * component — explicitly denied core.manage on com_datacompliance.
 *
 * @since 4.1.0
 */
class BackendAccessTest extends AbstractE2ETestCase
{
	/**
	 * Every back-end view other than Options needs core.manage.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testViewsNeedCoreManage(): void
	{
		$nomanage = $this->loggedInBackend('nomanage');
		$allowed  = $this->loggedInBackend('administrator');

		foreach (['controlpanel', 'consenttrails', 'exporttrails', 'usertrails', 'wipetrails', 'lifecycle', 'emailtemplates'] as $view)
		{
			$response = $nomanage->get($this->adminUrl(['view' => $view]));

			$this->assertRefused($nomanage, $response, sprintf('view=%s without core.manage', $view));

			// The control: the same page for an Administrator who does hold core.manage.
			$control = $allowed->get($this->adminUrl(['view' => $view]));

			$this->assertStatus(200, $control, sprintf('view=%s with core.manage must render', $view));
			$this->assertNotServerError($control);
		}
	}

	/**
	 * The Options view stays reachable for any back-end user; it is the self-service page.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testOptionsViewIsReachableWithoutCoreManage(): void
	{
		$nomanage = $this->loggedInBackend('nomanage');
		$response = $nomanage->get($this->adminUrl(['view' => 'options']));

		$this->assertStatus(200, $response);
		$this->assertBodyContains('E2E-PRIVACY-POLICY-INTRO', $response, 'The Options page shows the privacy policy article.');
		$this->assertNoTokenInLinks($response);
	}

	/**
	 * H1: view=options must not exempt ANOTHER controller from the core.manage check.
	 *
	 * The attack: view=options&task=emailtemplates.resetEmails, with the attacker's own token.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testViewOptionsDoesNotUnlockOtherControllers(): void
	{
		$this->plantMailTemplateMarker();

		try
		{
			$nomanage = $this->loggedInBackend('nomanage');
			$token    = $this->tokenFor($nomanage, true);

			$response = $nomanage->post(
				$this->adminUrl(['view' => 'options', 'task' => 'emailtemplates.resetEmails']),
				[$token => 1]
			);

			$this->assertRefused($nomanage, $response, 'emailtemplates.resetEmails via view=options');
			$this->assertSame(1, $this->countMailTemplateMarkers(), 'The mail templates were reset by a user without core.manage.');

			// The same trick against the control panel's statistics.
			foreach (['controlpanel.userstats', 'controlpanel.wipedstats'] as $task)
			{
				$response = $nomanage->get($this->adminUrl(['view' => 'options', 'task' => $task]));

				$this->assertRefused($nomanage, $response, $task . ' via view=options');
				$this->assertNull($response->json(), $task . ' returned its JSON to a user without core.manage.');
			}

			// So is naming the controller explicitly.
			$response = $nomanage->post(
				$this->adminUrl(['view' => 'options', 'controller' => 'emailtemplates', 'task' => 'resetEmails']),
				[$token => 1]
			);

			$this->assertRefused($nomanage, $response, 'controller=emailtemplates via view=options');
			$this->assertSame(1, $this->countMailTemplateMarkers(), 'The mail templates were reset by a user without core.manage.');

			// The control: a Super User doing exactly the same (minus the trick) does reset them, so the
			// marker assertions above could have failed.
			$super = $this->superUser();
			$super->post($this->adminUrl(['task' => 'emailtemplates.resetEmails']), [$this->tokenFor($super, true) => 1]);

			$this->assertSame(0, $this->countMailTemplateMarkers(), 'The control reset did not reset the templates.');
		}
		finally
		{
			$this->clearMailTemplateMarkers();
		}
	}

	/**
	 * L2: the email template tasks take their token from POST only.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testEmailTemplateTasksRefuseTokenInUrl(): void
	{
		$this->plantMailTemplateMarker();

		try
		{
			$super = $this->superUser();
			$token = $this->tokenFor($super, true);

			$response = $super->get($this->adminUrl(['task' => 'emailtemplates.resetEmails', $token => 1]));

			$this->assertRefused($super, $response, 'resetEmails with the token in the URL');
			$this->assertSame(1, $this->countMailTemplateMarkers(), 'A GET request reset the mail templates.');

			$page = $super->get($this->adminUrl(['view' => 'emailtemplates']));

			$this->assertStatus(200, $page);
			$this->assertNoTokenInLinks($page);
		}
		finally
		{
			$this->clearMailTemplateMarkers();
		}
	}

	/**
	 * The control panel statistics answer JSON to a user with core.manage.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testControlPanelStatisticsForCoreManage(): void
	{
		$administrator = $this->loggedInBackend('administrator');

		foreach (['controlpanel.userstats', 'controlpanel.wipedstats'] as $task)
		{
			$response = $administrator->get($this->adminUrl(['task' => $task]));

			$this->assertStatus(200, $response, $task);
			$this->assertIsArray($response->json(), $task . ' did not return JSON.');
		}
	}

	/**
	 * The trail views are browse-only: their state-changing tasks are unregistered, even for a Super
	 * User, and the audit records survive the attempt.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function testTrailsAreBrowseOnly(): void
	{
		$super   = $this->superUser();
		$token   = $this->tokenFor($super, true);
		$aliceId = static::$fixtures->userId('alice');

		// One record in each trail, so there is something to delete.
		$this->db()->insert('#__datacompliance_wipetrails', [
			'user_id' => $aliceId, 'type' => 'admin', 'created_on' => gmdate('Y-m-d H:i:s'), 'created_by' => $aliceId,
			'requester_ip' => '192.0.2.9', 'items' => '{}',
		]);
		$wipeId = (int) $this->db()->value('SELECT MAX(datacompliance_wipetrail_id) FROM #__datacompliance_wipetrails');

		$this->db()->insert('#__datacompliance_usertrails', [
			'user_id' => $aliceId, 'created_on' => gmdate('Y-m-d H:i:s'), 'created_by' => $aliceId,
			'requester_ip' => '192.0.2.9', 'items' => '{}',
		]);
		$userTrailId = (int) $this->db()->value('SELECT MAX(datacompliance_usertrail_id) FROM #__datacompliance_usertrails');

		try
		{
			foreach (['wipetrails' => $wipeId, 'usertrails' => $userTrailId] as $controller => $id)
			{
				foreach (['delete', 'publish', 'unpublish', 'trash', 'archive', 'checkin'] as $task)
				{
					$response = $super->post(
						$this->adminUrl(['task' => $controller . '.' . $task]),
						['cid' => [$id], $token => 1]
					);

					// An unregistered task falls through to the controller's default task (display), which
					// is harmless. What matters is that nothing was changed; asserted below.
					$this->assertNotServerError($response, $controller . '.' . $task);
				}
			}

			$this->assertSame(1, (int) $this->db()->value('SELECT COUNT(*) FROM #__datacompliance_wipetrails WHERE datacompliance_wipetrail_id = ?', [$wipeId]), 'A wipe trail was deleted.');
			$this->assertSame(1, (int) $this->db()->value('SELECT COUNT(*) FROM #__datacompliance_usertrails WHERE datacompliance_usertrail_id = ?', [$userTrailId]), 'A user trail was deleted.');
		}
		finally
		{
			$this->db()->query('DELETE FROM #__datacompliance_wipetrails WHERE datacompliance_wipetrail_id = ?', [$wipeId]);
			$this->db()->query('DELETE FROM #__datacompliance_usertrails WHERE datacompliance_usertrail_id = ?', [$userTrailId]);
		}
	}

	/**
	 * Replace one mail template's subject with a marker, as an administrator's customisation would.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function plantMailTemplateMarker(): void
	{
		$this->db()->query(
			"UPDATE #__mail_templates SET subject = 'E2E-CUSTOMISED-SUBJECT' WHERE template_id = 'com_datacompliance.user_user'"
		);

		$this->assertSame(1, $this->countMailTemplateMarkers(), 'Could not plant the mail template marker.');
	}

	/**
	 * How many mail templates carry the marker.
	 *
	 * @return  int
	 * @since   4.1.0
	 */
	private function countMailTemplateMarkers(): int
	{
		return (int) $this->db()->value(
			"SELECT COUNT(*) FROM #__mail_templates WHERE subject = 'E2E-CUSTOMISED-SUBJECT'"
		);
	}

	/**
	 * Put the marked template back the way the component installs it.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function clearMailTemplateMarkers(): void
	{
		$this->db()->query(
			"UPDATE #__mail_templates SET subject = 'COM_DATACOMPLIANCE_MAIL_USER_USER_SUBJECT' WHERE subject = 'E2E-CUSTOMISED-SUBJECT'"
		);
	}
}
