<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Nested-set fixture provisioner for the Data Compliance end-to-end test suite.
 *
 * SiteProvisioner copies this file into the throwaway site's document root and runs it inside the
 * php container. It creates ONLY the fixtures that live in Joomla's nested sets or asset tree —
 * user groups, the component's permission rules, the ATS category, a user custom field and the
 * privacy policy article — and prints a JSON manifest of their ids on stdout. Everything flat (users,
 * group maps, consent records, component and plugin parameters, ATS and ARS rows) is seeded by the
 * host-side SiteProvisioner over PDO, which is faster and keeps timestamps exactly where the tests
 * need them.
 *
 * WHY THIS PART RUNS INSIDE THE CONTAINER
 *
 * User groups, categories, fields and assets are nested sets with an asset tree hanging off them.
 * Building those with hand-written INSERTs means reimplementing lft/rgt bookkeeping, getting it
 * subtly wrong, and then debugging ACL results that are wrong for reasons that have nothing to do
 * with Data Compliance. Joomla's own Table classes already do it correctly.
 *
 * Idempotent: every item is found by name and reused, so re-running it (a fixture reset) never
 * grows the tree.
 *
 * THE GROUP MATRIX IS THE POINT
 *
 * Each group exists because an authorisation rule in OptionsController::assertUserAccess() or the
 * back-end Dispatcher turns on it:
 *
 *   DC Exporters        Registered + `export` on com_datacompliance.
 *   DC Wipers           Registered + `wipe`.
 *   DC Administrators   Registered + `core.admin` (and `core.manage`) on com_datacompliance. The
 *                       "DataCompliance administrator" of the permission model; NOT a Super User.
 *   DC Exempt           Registered; listed in plg_datacompliance_joomla's "exempt user groups".
 *   Backend Without DC  Administrator (so: back-end login, and core.manage inherited from the root
 *                       asset), explicitly DENIED core.manage on com_datacompliance.
 *
 * Plain Administrators are used as they come: in a default Joomla install they inherit core.manage
 * on every component from the root asset (Managers do not; they are granted it per component), which
 * is precisely the privilege that must NOT stand in for export or wipe (M1).
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\Table\Category;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Usergroup;
use Joomla\Console\Application;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;

const _JEXEC = 1;

// ---------------------------------------------------------------------------
// Bootstrap, mirroring cli/joomla.php.
// ---------------------------------------------------------------------------
if (file_exists(__DIR__ . '/defines.php'))
{
	require_once __DIR__ . '/defines.php';
}

if (!defined('_JDEFINES'))
{
	define('JPATH_BASE', __DIR__);
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once JPATH_BASE . '/includes/framework.php';

$container = Factory::getContainer();

$container->alias('session', 'session.cli')
	->alias('JSession', 'session.cli')
	->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
	->alias(\Joomla\Session\Session::class, 'session.cli')
	->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

$app                  = $container->get(Application::class);
Factory::$application = $app;

/**
 * Load the extension PSR-4 map by hand.
 *
 * Nothing in libraries/bootstrap.php or includes/framework.php registers the extension namespaces.
 * That is done by ExtensionNamespaceMapper::createExtensionNamespaceMap(), which the console
 * application only calls from doExecute(). This script never executes the application, so without
 * this line NO extension class is loadable — and the failure is silent and misleading, because every
 * extension file guards itself with `defined('_JEXEC') or die`.
 */
$app->createExtensionNamespaceMap();

/** @var DatabaseDriver $db */
$db = $container->get(DatabaseInterface::class);

// ---------------------------------------------------------------------------
// Small helpers.
// ---------------------------------------------------------------------------

/**
 * Find a user group by title, or create it under the given parent.
 */
function ensureGroup(DatabaseDriver $db, string $title, int $parentId): int
{
	$query = $db->createQuery()
		->select($db->quoteName('id'))
		->from($db->quoteName('#__usergroups'))
		->where($db->quoteName('title') . ' = :title')
		->bind(':title', $title);

	$existing = $db->setQuery($query)->loadResult();
	$table    = new Usergroup($db);

	if ($existing)
	{
		// Reused from an earlier run; make sure it still hangs where the matrix says it must.
		$table->load((int) $existing);

		if ((int) $table->parent_id !== $parentId)
		{
			$table->parent_id = $parentId;

			if (!$table->store())
			{
				throw new RuntimeException(sprintf('Could not move user group "%s": %s', $title, $table->getError()));
			}

			$table->rebuild();
		}

		return (int) $existing;
	}

	if (!$table->save(['title' => $title, 'parent_id' => $parentId]))
	{
		throw new RuntimeException(sprintf('Could not create user group "%s": %s', $title, $table->getError()));
	}

	return (int) $table->id;
}

/**
 * Replace the permission rules of a named asset. The rules column is not part of the nested set, but
 * going through the Table keeps us honest about the asset actually existing.
 */
function setAssetRules(DatabaseDriver $db, string $assetName, array $rules): void
{
	$asset = new Asset($db);

	if (!$asset->loadByName($assetName))
	{
		throw new RuntimeException(sprintf('No such asset: "%s".', $assetName));
	}

	$asset->rules = json_encode((object) $rules);

	if (!$asset->store())
	{
		throw new RuntimeException(sprintf('Could not store the rules of asset "%s": %s', $assetName, $asset->getError()));
	}
}

/**
 * Is an extension installed?
 */
function hasComponent(DatabaseDriver $db, string $element): bool
{
	$query = $db->createQuery()
		->select('COUNT(*)')
		->from($db->quoteName('#__extensions'))
		->where($db->quoteName('type') . ' = ' . $db->quote('component'))
		->where($db->quoteName('element') . ' = :element')
		->bind(':element', $element);

	return (int) $db->setQuery($query)->loadResult() > 0;
}

// ---------------------------------------------------------------------------
// 1. User groups.
// ---------------------------------------------------------------------------
$registered    = 2;
$administrator = 7;

$groups = [
	'exporters'        => ensureGroup($db, 'DC Exporters', $registered),
	'wipers'           => ensureGroup($db, 'DC Wipers', $registered),
	'dcAdmins'         => ensureGroup($db, 'DC Administrators', $registered),
	'exempt'           => ensureGroup($db, 'DC Exempt', $registered),
	'backendWithoutDc' => ensureGroup($db, 'Backend Without DC', $administrator),
	'registered'       => $registered,
	'administrator'    => $administrator,
	'superUsers'       => 8,
];

// ---------------------------------------------------------------------------
// 2. The component's permissions.
// ---------------------------------------------------------------------------
setAssetRules(
	$db,
	'com_datacompliance',
	[
		'core.admin'  => [$groups['dcAdmins'] => 1],
		'core.manage' => [$groups['dcAdmins'] => 1, $groups['backendWithoutDc'] => 0],
		'export'      => [$groups['exporters'] => 1],
		'wipe'        => [$groups['wipers'] => 1],
	]
);

// ---------------------------------------------------------------------------
// 3. The privacy policy article shown on the Options page.
// ---------------------------------------------------------------------------
$articleAlias = 'e2e-privacy-policy';
$articleId    = (int) $db->setQuery(
	$db->createQuery()
		->select($db->quoteName('id'))
		->from($db->quoteName('#__content'))
		->where($db->quoteName('alias') . ' = :alias')
		->bind(':alias', $articleAlias)
)->loadResult();

if (!$articleId)
{
	$article = new Content($db);
	$now     = Factory::getDate()->toSql();

	$ok = $article->save([
		'title'      => 'E2E Privacy Policy',
		'alias'      => $articleAlias,
		'introtext'  => '<p>E2E-PRIVACY-POLICY-INTRO</p>',
		'fulltext'   => '<p>E2E-PRIVACY-POLICY-FULL</p>',
		'state'      => 1,
		'catid'      => 2,
		'access'     => 1,
		'language'   => '*',
		'created'    => $now,
		'created_by' => 0,
		'publish_up' => $now,
		'attribs'    => '{}',
		'metadata'   => '{}',
		'metakey'    => '',
		'metadesc'   => '',
		'images'     => '{}',
		'urls'       => '{}',
	]);

	if (!$ok)
	{
		throw new RuntimeException('Could not create the privacy policy article: ' . $article->getError());
	}

	$articleId = (int) $article->id;
}

// ---------------------------------------------------------------------------
// 4. A user custom field (com_fields), so profile edits and wipes have custom field values to act on.
// ---------------------------------------------------------------------------
$fieldId   = 0;
$fieldName = 'e2e-phone';

$fieldId = (int) $db->setQuery(
	$db->createQuery()
		->select($db->quoteName('id'))
		->from($db->quoteName('#__fields'))
		->where($db->quoteName('name') . ' = :name')
		->where($db->quoteName('context') . ' = ' . $db->quote('com_users.user'))
		->bind(':name', $fieldName)
)->loadResult();

if (!$fieldId)
{
	$fieldTableClass = \Joomla\Component\Fields\Administrator\Table\FieldTable::class;
	$field           = new $fieldTableClass($db);

	$ok = $field->save([
		'context'       => 'com_users.user',
		'group_id'      => 0,
		'title'         => 'E2E Phone',
		'name'          => $fieldName,
		'label'         => 'E2E Phone',
		'default_value' => '',
		'type'          => 'text',
		'note'          => '',
		'description'   => '',
		'state'         => 1,
		'required'      => 0,
		'only_use_in_subform' => 0,
		'access'        => 1,
		'language'      => '*',
		'params'        => '{"show_on":"","display":"2","display_readonly":"2"}',
		'fieldparams'   => '{"filter":"","maxlength":""}',
		'created_time'  => Factory::getDate()->toSql(),
		'created_user_id' => 0,
		// Anyone who can edit their own profile may set its value.
		'rules'         => ['core.edit.value' => [$registered => 1]],
	]);

	if (!$ok)
	{
		throw new RuntimeException('Could not create the user custom field: ' . $field->getError());
	}

	$fieldId = (int) $field->id;
}

// ---------------------------------------------------------------------------
// 5. The ATS category, when ATS is installed.
// ---------------------------------------------------------------------------
$atsCategoryId = 0;

if (hasComponent($db, 'com_ats'))
{
	$atsCategoryId = (int) $db->setQuery(
		$db->createQuery()
			->select($db->quoteName('id'))
			->from($db->quoteName('#__categories'))
			->where($db->quoteName('extension') . ' = ' . $db->quote('com_ats'))
			->where($db->quoteName('alias') . ' = ' . $db->quote('e2e-support'))
	)->loadResult();

	if (!$atsCategoryId)
	{
		$category = new Category($db);
		$category->setLocation(1, 'last-child');

		$ok = $category->save([
			'parent_id'   => 1,
			'extension'   => 'com_ats',
			'title'       => 'E2E Support',
			'alias'       => 'e2e-support',
			'published'   => 1,
			'access'      => 1,
			'language'    => '*',
			'params'      => '{}',
			'metadata'    => '{}',
			'description' => '',
		]);

		if (!$ok)
		{
			throw new RuntimeException('Could not create the ATS category: ' . $category->getError());
		}

		$atsCategoryId = (int) $category->id;
	}
}

// ---------------------------------------------------------------------------
// 6. Manifest, for the host-side provisioner.
// ---------------------------------------------------------------------------
echo json_encode(
	[
		'groups'        => $groups,
		'articleId'     => $articleId,
		'fieldId'       => $fieldId,
		'fieldName'     => $fieldName,
		'atsCategoryId' => $atsCategoryId,
		'hasAts'        => hasComponent($db, 'com_ats'),
		'hasArs'        => hasComponent($db, 'com_ars'),
	],
	JSON_PRETTY_PRINT
);
