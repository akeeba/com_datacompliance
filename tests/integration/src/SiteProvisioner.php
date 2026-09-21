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
use RuntimeException;

/**
 * Creates, resets and reads back the Data Compliance fixtures on the provisioned site.
 *
 * Two halves:
 *
 *   - The nested-set fixtures (user groups, the component's permission rules, the ATS category, a
 *     user custom field, the privacy policy article) are built INSIDE the php container by
 *     assets/e2e-provision.php, with Joomla's own Table classes. See that file for why.
 *   - Everything flat — users, group maps, consent records, component and plugin parameters, ATS
 *     and ARS rows — is seeded from here over PDO.
 *
 * Wiping an account is irreversible, so tests that wipe never touch the shared accounts below: they
 * ask for a throwaway one with {@see createUser()}.
 *
 * THE ROLES
 *
 *   alice, bob   Registered, consented. Data subjects; bob is the "someone else" of every
 *                cross-user attempt.
 *   carol        Registered, has NOT consented. The consent redirect's subject.
 *   exporter     DC Exporters: `export` only.
 *   wiper        DC Wipers: `wipe` only.
 *   dcadmin      DC Administrators: core.admin on com_datacompliance, not a Super User.
 *   administrator  Administrator: back-end login, core.manage on the component inherited from
 *                the root asset, and nothing else. Must not be able to export or wipe anyone
 *                but themselves (M1).
 *   nomanage     Backend Without DC: an Administrator explicitly denied core.manage on the
 *                component.
 *   super2       A second Super User, the "protected target" of the Super User rules.
 *   exempt       DC Exempt: in plg_datacompliance_joomla's exempt user groups.
 *   admin        The installer's Super User (not recreated; see config).
 *
 * @since 4.1.0
 */
class SiteProvisioner
{
	/**
	 * Filename of the in-container provisioning script inside the site root.
	 *
	 * @since 4.1.0
	 */
	private const SCRIPT = 'e2e-provision.php';

	/**
	 * Filename of the manifest, inside the site root.
	 *
	 * @since 4.1.0
	 */
	private const MANIFEST = 'e2e-manifest.json';

	/**
	 * The shared accounts, role => [display name, group keys, consented?].
	 *
	 * @since 4.1.0
	 */
	private const ROLES = [
		'alice'    => ['Alice Example', ['registered'], true],
		'bob'      => ['Bob Example', ['registered'], true],
		'carol'    => ['Carol Example', ['registered'], false],
		'exporter' => ['Erin Exporter', ['registered', 'exporters'], true],
		'wiper'    => ['Walter Wiper', ['registered', 'wipers'], true],
		'dcadmin'  => ['Dana Administrator', ['registered', 'dcAdmins'], true],
		'administrator' => ['Ada Administrator', ['administrator'], true],
		'nomanage' => ['Nico Nomanage', ['backendWithoutDc'], true],
		'super2'   => ['Sam Superuser', ['superUsers'], true],
		'exempt'   => ['Eddie Exempt', ['registered', 'exempt'], true],
	];

	/**
	 * The component's parameters after a reset: its shipped defaults, plus the policy article.
	 *
	 * @since 4.1.0
	 */
	private const COMPONENT_PARAMS = [
		'showexport'              => 1,
		'maximalist_export'       => 1,
		'showwipe'                => 1,
		'workaround_mailtemplate' => 1,
	];

	/**
	 * The suite configuration.
	 *
	 * @var   Configuration
	 * @since 4.1.0
	 */
	private Configuration $config;

	/**
	 * The manifest of everything provisioned.
	 *
	 * @var   array|null
	 * @since 4.1.0
	 */
	private ?array $manifest = null;

	/**
	 * Shared instance.
	 *
	 * @var   self|null
	 * @since 4.1.0
	 */
	private static ?self $instance = null;

	/**
	 * Database connection, created on first use.
	 *
	 * @var   Database|null
	 * @since 4.1.0
	 */
	private ?Database $db = null;

	/**
	 * Constructor.
	 *
	 * @param   Configuration|null  $config  The suite configuration.
	 *
	 * @since   4.1.0
	 */
	public function __construct(?Configuration $config = null)
	{
		$this->config = $config ?? Configuration::getInstance();
	}

	/**
	 * The shared instance, so a whole PHPUnit run provisions once by default.
	 *
	 * @return  self
	 * @since   4.1.0
	 */
	public static function getInstance(): self
	{
		return self::$instance ??= new self();
	}

	/**
	 * Provision the fixtures from scratch. Re-running is a reset, not a duplication.
	 *
	 * @return  array  The manifest.
	 * @since   4.1.0
	 */
	public function provision(): array
	{
		$nested = $this->runNestedSetProvisioner();
		$db     = $this->db();

		$manifest = [
			'groups'        => $nested['groups'],
			'articleId'     => (int) $nested['articleId'],
			'fieldId'       => (int) $nested['fieldId'],
			'fieldName'     => (string) $nested['fieldName'],
			'atsCategoryId' => (int) $nested['atsCategoryId'],
			'hasAts'        => (bool) $nested['hasAts'],
			'hasArs'        => (bool) $nested['hasArs'],
			'users'         => [],
			'usernames'     => [],
			'emails'        => [],
			'ats'           => [],
			'ars'           => [],
		];

		// Everyone out. Sessions of accounts about to be deleted would otherwise linger.
		$db->query('DELETE FROM #__session');

		$this->deleteAllUsersButTheSuperUser();

		// The Data Compliance audit tables start empty on every reset.
		foreach (['consenttrails', 'exporttrails', 'usertrails', 'wipetrails'] as $table)
		{
			$db->query('DELETE FROM #__datacompliance_' . $table);
		}

		// The installer's Super User.
		[$adminUsername] = $this->config->getAdminCredentials();
		$adminId         = (int) $db->value('SELECT id FROM #__users WHERE username = ?', [$adminUsername]);

		if ($adminId <= 0)
		{
			throw new RuntimeException(sprintf('The Super User "%s" does not exist.', $adminUsername));
		}

		$db->query(
			'UPDATE #__users SET sendEmail = 1, block = 0, requireReset = 0, lastvisitDate = UTC_TIMESTAMP() WHERE id = ?',
			[$adminId]
		);
		$this->recordConsent($adminId);

		$manifest['users']['admin']     = $adminId;
		$manifest['usernames']['admin'] = $adminUsername;
		$manifest['emails']['admin']    = (string) $db->value('SELECT email FROM #__users WHERE id = ?', [$adminId]);

		// The shared accounts.
		foreach (self::ROLES as $role => [$name, $groupKeys, $consented])
		{
			$groupIds = array_map(fn(string $key): int => (int) $nested['groups'][$key], $groupKeys);
			$id       = $this->createUser([
				'username'  => $role,
				'name'      => $name,
				'email'     => $role . '@example.test',
				'groups'    => $groupIds,
				'consent'   => $consented,
				// Only the second Super User receives the administrators' notifications besides admin.
				'sendEmail' => $role === 'super2' ? 1 : 0,
			]);

			$manifest['users'][$role]     = $id;
			$manifest['usernames'][$role] = $role;
			$manifest['emails'][$role]    = $role . '@example.test';
		}

		$this->resetParameters($manifest);

		if ($manifest['hasAts'])
		{
			foreach (['#__ats_attachments', '#__ats_posts', '#__ats_managernotes', '#__ats_tickets_users', '#__ats_tickets'] as $table)
			{
				$db->query('DELETE FROM ' . $table);
			}

			$this->removeAtsAttachmentFiles();

			$manifest['ats']['alice'] = $this->seedAtsFor($manifest['users']['alice'], $manifest, 'alice');
		}

		if ($manifest['hasArs'])
		{
			$db->query('DELETE FROM #__ars_log');
			$db->query('DELETE FROM #__ars_dlidlabels');

			$manifest['ars']['alice'] = $this->seedArsFor($manifest['users']['alice']);
		}

		SiteProbe::deploy($this->config);

		$this->writeManifest($manifest);

		return $this->manifest = $manifest;
	}

	/**
	 * Re-run the provisioner, discarding whatever the tests have done to the fixtures.
	 *
	 * @return  array  The manifest.
	 * @since   4.1.0
	 */
	public function reset(): array
	{
		return $this->provision();
	}

	/**
	 * The manifest, provisioning first if it has not been done yet on this site.
	 *
	 * @return  array
	 * @since   4.1.0
	 */
	public function getManifest(): array
	{
		if ($this->manifest !== null)
		{
			return $this->manifest;
		}

		$file = $this->siteRoot() . '/' . self::MANIFEST;

		if (is_file($file))
		{
			$data = json_decode((string) file_get_contents($file), true);

			if (is_array($data))
			{
				return $this->manifest = $data;
			}
		}

		return $this->provision();
	}

	/**
	 * The numeric id of a shared account, by role.
	 *
	 * @param   string  $role  A role, e.g. 'alice', 'exporter', 'admin'.
	 *
	 * @return  int
	 * @since   4.1.0
	 */
	public function userId(string $role): int
	{
		return (int) $this->lookup('users', $role);
	}

	/**
	 * The username of a shared account, by role.
	 *
	 * @param   string  $role  A role.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	public function username(string $role): string
	{
		return (string) $this->lookup('usernames', $role);
	}

	/**
	 * The email address of a shared account, by role.
	 *
	 * @param   string  $role  A role.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	public function email(string $role): string
	{
		return (string) $this->lookup('emails', $role);
	}

	/**
	 * The id of a provisioned user group.
	 *
	 * @param   string  $name  e.g. 'exporters', 'wipers', 'exempt', 'registered'.
	 *
	 * @return  int
	 * @since   4.1.0
	 */
	public function groupId(string $name): int
	{
		return (int) $this->lookup('groups', $name);
	}

	/**
	 * Create an account directly in the database: the user row, its group map, and (optionally) a
	 * consent record, so the system plugin does not turn every request into a consent redirect.
	 *
	 * Recognised keys: username, name, email, password, groups (ids; default Registered), consent
	 * (bool, default true), block, sendEmail, registerDate, lastvisitDate (SQL datetime, or null for
	 * "never visited"; default now), params (JSON string), activation.
	 *
	 * @param   array  $spec  What to create.
	 *
	 * @return  int  The new user's id.
	 * @since   4.1.0
	 */
	public function createUser(array $spec = []): int
	{
		$db       = $this->db();
		$suffix   = bin2hex(random_bytes(4));
		$username = $spec['username'] ?? ('victim' . $suffix);
		$now      = gmdate('Y-m-d H:i:s');

		$row = [
			'name'          => $spec['name'] ?? ('Victim ' . $suffix),
			'username'      => $username,
			'email'         => $spec['email'] ?? ($username . '@example.test'),
			'password'      => password_hash($spec['password'] ?? $this->config->getUserPassword(), PASSWORD_BCRYPT),
			'block'         => (int) ($spec['block'] ?? 0),
			'sendEmail'     => (int) ($spec['sendEmail'] ?? 0),
			'registerDate'  => $spec['registerDate'] ?? gmdate('Y-m-d H:i:s', time() - 86400),
			'lastvisitDate' => array_key_exists('lastvisitDate', $spec) ? $spec['lastvisitDate'] : $now,
			'activation'    => $spec['activation'] ?? '',
			'params'        => $spec['params'] ?? '{}',
			'requireReset'  => 0,
			'resetCount'    => 0,
		];

		$id = $db->insert('#__users', $row);

		foreach ($spec['groups'] ?? [2] as $groupId)
		{
			$db->insert('#__user_usergroup_map', ['user_id' => $id, 'group_id' => (int) $groupId]);
		}

		if ($spec['consent'] ?? true)
		{
			$this->recordConsent($id);
		}

		return $id;
	}

	/**
	 * Give an account the personal data the Joomla core plugin promises to remove on wipe: a user
	 * note, a profile row, a remember-me key, and a value for the custom field.
	 *
	 * @param   int  $userId  The account.
	 *
	 * @return  array{note: int, profileKey: string, keySeries: string, fieldValue: string}
	 * @since   4.1.0
	 */
	public function seedPersonalData(int $userId): array
	{
		$db       = $this->db();
		$manifest = $this->getManifest();
		$now      = gmdate('Y-m-d H:i:s');
		$series   = 'e2e-series-' . bin2hex(random_bytes(8));

		$noteId = $db->insert('#__user_notes', [
			'user_id'          => $userId,
			'catid'            => 0,
			'subject'          => 'E2E note about user ' . $userId,
			'body'             => 'E2E-NOTE-BODY-' . $userId,
			'state'            => 1,
			'checked_out'      => null,
			'checked_out_time' => null,
			'created_user_id'  => $manifest['users']['admin'],
			'created_time'     => $now,
			'modified_user_id' => 0,
			'modified_time'    => $now,
			'review_time'      => null,
			'publish_up'       => null,
			'publish_down'     => null,
		]);

		$db->query(
			'INSERT INTO #__user_profiles (user_id, profile_key, profile_value, ordering) VALUES (?, ?, ?, 1)',
			[$userId, 'profile.city', json_encode('E2E-CITY-' . $userId)]
		);

		$db->query(
			'INSERT INTO #__user_keys (user_id, token, series, time, uastring) VALUES (?, ?, ?, ?, ?)',
			[(string) $db->value('SELECT username FROM #__users WHERE id = ?', [$userId]), password_hash('e2e-token', PASSWORD_BCRYPT), $series, (string) time(), 'E2E-UA']
		);

		$fieldValue = 'E2E-PHONE-' . $userId;

		if (!empty($manifest['fieldId']))
		{
			$db->query(
				'INSERT INTO #__fields_values (field_id, item_id, value) VALUES (?, ?, ?)',
				[$manifest['fieldId'], (string) $userId, $fieldValue]
			);
		}

		return [
			'note'       => $noteId,
			'profileKey' => 'profile.city',
			'keySeries'  => $series,
			'fieldValue' => $fieldValue,
		];
	}

	/**
	 * Seed the ATS data a user owns: a private ticket (the user's post with an attachment on disk, a
	 * staff reply, a manager note), a public ticket, and an invitation to someone else's ticket.
	 *
	 * @param   int         $userId    The ticket owner.
	 * @param   array|null  $manifest  The manifest (during provisioning, before it is written).
	 * @param   string      $label     Used in the fixture text, so leftovers are traceable.
	 *
	 * @return  array  Ids and the attachment file path (relative to the site root).
	 * @since   4.1.0
	 */
	public function seedAtsFor(int $userId, ?array $manifest = null, string $label = ''): array
	{
		$manifest = $manifest ?? $this->getManifest();
		$db       = $this->db();
		$catId    = (int) $manifest['atsCategoryId'];
		$staffId  = (int) $manifest['users']['admin'];
		$otherId  = (int) $manifest['users']['bob'];
		$label    = $label ?: ('user' . $userId);
		$now      = gmdate('Y-m-d H:i:s');

		$ticket = fn(int $owner, int $public, string $title): int => $db->insert('#__ats_tickets', [
			'catid'       => $catId,
			'status'      => 'O',
			'title'       => $title,
			'alias'       => 'e2e-' . bin2hex(random_bytes(6)),
			'public'      => $public,
			'priority'    => 5,
			'origin'      => 'web',
			'assigned_to' => 0,
			'timespent'   => 0,
			'created'     => $now,
			'created_by'  => $owner,
			'modified'    => $now,
			'modified_by' => $owner,
			'enabled'     => 1,
			'params'      => '{}',
		]);

		$post = fn(int $ticketId, int $author, string $html): int => $db->insert('#__ats_posts', [
			'attachment_id' => '0',
			'ticket_id'     => $ticketId,
			'content_html'  => $html,
			'origin'        => 'web',
			'timespent'     => 0,
			'created'       => $now,
			'created_by'    => $author,
			'modified'      => $now,
			'modified_by'   => $author,
			'enabled'       => 1,
		]);

		$private     = $ticket($userId, 0, 'E2E private ticket of ' . $label);
		$public      = $ticket($userId, 1, 'E2E public ticket of ' . $label);
		$othersOwn   = $ticket($otherId, 0, 'E2E ticket of bob, ' . $label . ' invited');
		$ownPost     = $post($private, $userId, '<p>E2E-ATS-USER-POST-' . $label . '</p>');
		$staffPost   = $post($private, $staffId, '<p>E2E-ATS-STAFF-REPLY-' . $label . '</p>');
		$publicPost  = $post($public, $userId, '<p>E2E-ATS-PUBLIC-POST-' . $label . '</p>');
		$othersPost  = $post($othersOwn, $otherId, '<p>E2E-ATS-BOB-POST-' . $label . '</p>');

		// An attachment, stored the way ATS stores them: a sha1 "mangled" name under a two-level
		// ab/cd/ prefix taken from that same name, in the default attachments directory.
		$mangled  = hash('sha1', 'dc-e2e-attachment-' . $label . '-' . bin2hex(random_bytes(4)));
		$relative = 'media/com_ats/attachments/' . substr($mangled, 0, 2) . '/' . substr($mangled, 2, 2) . '/' . $mangled;
		$absolute = $this->siteRoot() . '/' . $relative;

		if (!is_dir(\dirname($absolute)))
		{
			mkdir(\dirname($absolute), 0755, true);
		}

		file_put_contents($absolute, "E2E-ATS-ATTACHMENT-" . $label . "\n");

		$attachment = $db->insert('#__ats_attachments', [
			'post_id'           => $ownPost,
			'original_filename' => 'e2e-' . $label . '.txt',
			'mangled_filename'  => $mangled,
			'mime_type'         => 'text/plain',
			'origin'            => 'web',
			'created'           => $now,
			'created_by'        => $userId,
			'enabled'           => 1,
		]);

		$db->query('UPDATE #__ats_posts SET attachment_id = ? WHERE id = ?', [(string) $attachment, $ownPost]);

		$note = $db->insert('#__ats_managernotes', [
			'ticket_id'   => $private,
			'note_html'   => '<p>E2E-ATS-MANAGER-NOTE-about-' . $label . '</p>',
			'created'     => $now,
			'created_by'  => $staffId,
			'modified'    => $now,
			'modified_by' => $staffId,
			'enabled'     => 1,
		]);

		$invite = $db->insert('#__ats_tickets_users', ['ticket_id' => $othersOwn, 'user_id' => $userId]);

		return [
			'privateTicket'  => $private,
			'publicTicket'   => $public,
			'othersTicket'   => $othersOwn,
			'ownPost'        => $ownPost,
			'staffPost'      => $staffPost,
			'publicPost'     => $publicPost,
			'othersPost'     => $othersPost,
			'attachment'     => $attachment,
			'attachmentFile' => $relative,
			'managerNote'    => $note,
			'invite'         => $invite,
		];
	}

	/**
	 * Seed the ARS data a user owns: a download log entry and a Download ID.
	 *
	 * @param   int  $userId  The user.
	 *
	 * @return  array{log: int, dlid: int, dlidValue: string}
	 * @since   4.1.0
	 */
	public function seedArsFor(int $userId): array
	{
		$db    = $this->db();
		$value = bin2hex(random_bytes(16));

		$log = $db->insert('#__ars_log', [
			'user_id'     => $userId,
			'item_id'     => 1,
			'accessed_on' => gmdate('Y-m-d H:i:s'),
			'referer'     => 'https://example.test/e2e-referer',
			'ip'          => '192.0.2.44',
			'authorized'  => 1,
		]);

		$dlid = $db->insert('#__ars_dlidlabels', [
			'user_id'    => $userId,
			'primary'    => 1,
			'title'      => '',
			'dlid'       => $value,
			'published'  => 1,
			'created_by' => $userId,
			'created'    => gmdate('Y-m-d H:i:s'),
		]);

		return ['log' => $log, 'dlid' => $dlid, 'dlidValue' => $value];
	}

	/**
	 * Merge values into a component's parameters (Data Compliance's by default).
	 *
	 * @param   array   $params   Parameter => value.
	 * @param   string  $element  The component, e.g. 'com_mails'.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function setComponentParams(array $params, string $element = 'com_datacompliance'): void
	{
		$this->mergeExtensionParams(
			sprintf("type = 'component' AND element = %s", $this->db()->getPdo()->quote($element)),
			$params
		);
	}

	/**
	 * Merge values into a plugin's parameters, and optionally enable or disable it.
	 *
	 * @param   string     $folder   The plugin group, e.g. 'datacompliance'.
	 * @param   string     $element  The plugin element, e.g. 'joomla'.
	 * @param   array      $params   Parameter => value.
	 * @param   bool|null  $enabled  Enable (true), disable (false) or leave alone (null).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function setPluginParams(string $folder, string $element, array $params, ?bool $enabled = null): void
	{
		$where = sprintf(
			"type = 'plugin' AND folder = %s AND element = %s",
			$this->db()->getPdo()->quote($folder),
			$this->db()->getPdo()->quote($element)
		);

		$this->mergeExtensionParams($where, $params);

		if ($enabled !== null)
		{
			$this->db()->query('UPDATE #__extensions SET enabled = ? WHERE ' . $where, [$enabled ? 1 : 0]);
		}
	}

	/**
	 * Give an account a Data Compliance consent record, the way OptionsModel::recordPreference()
	 * writes one.
	 *
	 * @param   int   $userId   The account.
	 * @param   bool  $enabled  Consented (true) or declined (false).
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	public function recordConsent(int $userId, bool $enabled = true): void
	{
		$db = $this->db();

		$db->query('DELETE FROM #__datacompliance_consenttrails WHERE created_by = ?', [$userId]);
		$db->insert('#__datacompliance_consenttrails', [
			'created_on'   => gmdate('Y-m-d H:i:s'),
			'created_by'   => $userId,
			'requester_ip' => '192.0.2.1',
			'enabled'      => $enabled ? 1 : 0,
		]);
	}

	/**
	 * Reset the component and plugin parameters to the suite's baseline.
	 *
	 * @param   array  $manifest  The manifest being built.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function resetParameters(array $manifest): void
	{
		$db = $this->db();

		$db->query(
			"UPDATE #__extensions SET params = ? WHERE type = 'component' AND element = 'com_datacompliance'",
			[json_encode(self::COMPONENT_PARAMS + ['policyarticle' => $manifest['articleId']])]
		);

		/**
		 * The package's plugins. Joomla installs new plugins disabled; a site owner enables what they
		 * use. This suite runs them all, except LoginGuard (the product it integrates with is not
		 * installed) and S3, which S3AuditTrailTest switches on for itself.
		 */
		$plugins = [
			['console', 'datacompliance', true, null],
			['system', 'datacompliance', true, ['exempt' => 'com_loginguard.*.*']],
			['user', 'datacompliance', true, null],
			['datacompliance', 'joomla', true, [
				'exemptgroups' => [(string) $manifest['groups']['exempt']],
				'lifecycle'    => 1,
				'threshold'    => 18,
				'nevervisited' => 1,
				'blocked'      => 1,
			]],
			['datacompliance', 'email', true, ['users' => 1, 'admins' => 1, 'adminemails' => '']],
			['datacompliance', 'ats', true, null],
			['datacompliance', 'ars', true, null],
			['datacompliance', 'loginguard', false, null],
			['datacompliance', 's3', false, null],
		];

		foreach ($plugins as [$folder, $element, $enabled, $params])
		{
			$db->query(
				"UPDATE #__extensions SET enabled = ?" . ($params === null ? '' : ', params = ?')
				. " WHERE type = 'plugin' AND folder = ? AND element = ?",
				$params === null
					? [$enabled ? 1 : 0, $folder, $element]
					: [$enabled ? 1 : 0, json_encode($params), $folder, $element]
			);
		}
	}

	/**
	 * Merge values into the params column of the matching #__extensions row.
	 *
	 * @param   string  $where   SQL condition selecting exactly one row.
	 * @param   array   $params  Parameter => value.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function mergeExtensionParams(string $where, array $params): void
	{
		$db  = $this->db();
		$raw = $db->value('SELECT params FROM #__extensions WHERE ' . $where);

		if ($raw === null)
		{
			throw new RuntimeException('No extension matches: ' . $where);
		}

		$current = json_decode((string) $raw, true) ?: [];

		$db->query(
			'UPDATE #__extensions SET params = ? WHERE ' . $where,
			[json_encode(array_merge($current, $params))]
		);
	}

	/**
	 * Delete every account except the installer's Super User, with everything hanging off them.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function deleteAllUsersButTheSuperUser(): void
	{
		$db              = $this->db();
		[$adminUsername] = $this->config->getAdminCredentials();

		$ids = array_map(
			'intval',
			$db->column('SELECT id FROM #__users WHERE username <> ?', [$adminUsername])
		);

		// user_keys is keyed by username, not id.
		$db->query('DELETE FROM #__user_keys WHERE user_id <> ?', [$adminUsername]);

		if ($ids === [])
		{
			return;
		}

		$in = implode(',', $ids);

		foreach (['#__user_usergroup_map' => 'user_id', '#__user_profiles' => 'user_id', '#__user_notes' => 'user_id', '#__privacy_consents' => 'user_id'] as $table => $column)
		{
			$db->query(sprintf('DELETE FROM %s WHERE %s IN (%s)', $table, $column, $in));
		}

		// MFA records exist from Joomla 4.2 on; ignore the table being absent.
		try
		{
			$db->query(sprintf('DELETE FROM #__user_mfa WHERE user_id IN (%s)', $in));
		}
		catch (\Throwable $e)
		{
		}

		$db->query(sprintf("DELETE FROM #__fields_values WHERE item_id IN ('%s')", implode("','", $ids)));
		$db->query(sprintf('DELETE FROM #__users WHERE id IN (%s)', $in));
	}

	/**
	 * Remove the attachment files a previous run seeded.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function removeAtsAttachmentFiles(): void
	{
		$dir = $this->siteRoot() . '/media/com_ats/attachments';

		if (!is_dir($dir))
		{
			return;
		}

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($iterator as $file)
		{
			// Keep ATS's own directory hardening (.htaccess, web.config, index.html).
			if ($file->isFile() && preg_match('/^[0-9a-f]{40}$/', $file->getFilename()))
			{
				@unlink($file->getPathname());
			}
		}
	}

	/**
	 * Run the in-container, nested-set part of the provisioning.
	 *
	 * @return  array  Its manifest.
	 * @since   4.1.0
	 */
	private function runNestedSetProvisioner(): array
	{
		$source = \dirname(__DIR__) . '/assets/' . self::SCRIPT;
		$target = $this->siteRoot() . '/' . self::SCRIPT;

		if (!copy($source, $target))
		{
			throw new RuntimeException(sprintf('Could not copy the provisioning script to %s.', $target));
		}

		[$exitCode, $output] = (new ContainerCli($this->config))->run(['php', self::SCRIPT]);

		// The JSON document is the last thing printed; anything before it is PHP noise worth showing.
		$start = strpos($output, "{\n");
		$data  = $start === false ? null : json_decode(substr($output, $start), true);

		if ($exitCode !== 0 || !is_array($data))
		{
			throw new RuntimeException(sprintf("Fixture provisioning failed (exit %d):\n%s", $exitCode, $output));
		}

		return $data;
	}

	/**
	 * Write the manifest into the site root, so later PHPUnit processes can read it without
	 * provisioning again.
	 *
	 * @param   array  $manifest  The manifest.
	 *
	 * @return  void
	 * @since   4.1.0
	 */
	private function writeManifest(array $manifest): void
	{
		file_put_contents(
			$this->siteRoot() . '/' . self::MANIFEST,
			json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		);
	}

	/**
	 * Read a value out of a manifest section, failing loudly when it is absent.
	 *
	 * A missing key here means the fixture the test needs was never created. Returning null and
	 * letting the test carry on would produce a request for user id 0, which the site quite
	 * correctly refuses — and the test would pass while proving nothing.
	 *
	 * @param   string  $section  The manifest section.
	 * @param   string  $key      The key within it.
	 *
	 * @return  mixed
	 * @since   4.1.0
	 */
	private function lookup(string $section, string $key)
	{
		$manifest = $this->getManifest();

		if (!isset($manifest[$section]) || !array_key_exists($key, $manifest[$section]))
		{
			throw new RuntimeException(
				sprintf(
					'The fixture manifest has no %s named "%s". Known: %s',
					rtrim($section, 's'),
					$key,
					implode(', ', array_keys($manifest[$section] ?? [])) ?: '(none)'
				)
			);
		}

		return $manifest[$section][$key];
	}

	/**
	 * The database connection.
	 *
	 * @return  Database
	 * @since   4.1.0
	 */
	private function db(): Database
	{
		return $this->db ??= new Database($this->config);
	}

	/**
	 * Absolute path to the provisioned site's document root on the host.
	 *
	 * @return  string
	 * @since   4.1.0
	 */
	private function siteRoot(): string
	{
		$root = rtrim($this->config->getSiteRoot(), '/');

		if (!is_dir($root))
		{
			throw new RuntimeException(
				sprintf('The site root %s does not exist. Run tests/integration/docker/run.sh first.', $root)
			);
		}

		return $root;
	}
}
