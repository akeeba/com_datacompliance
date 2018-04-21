<?php
/**
 * @package   AdminTools
 * @copyright Copyright (c)2010-2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 * @version   $Id$
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

// Load FOF if not already loaded
if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	throw new RuntimeException('This component requires FOF 3.0.');
}

class Com_DatacomplianceInstallerScript extends \FOF30\Utils\InstallScript
{
	/**
	 * The component's name
	 *
	 * @var   string
	 */
	protected $componentName = 'com_datacompliane';

	/**
	 * The title of the component (printed on installation and uninstallation messages)
	 *
	 * @var string
	 */
	protected $componentTitle = 'Akeeba Data Compliance';

	/**
	 * The minimum PHP version required to install this extension
	 *
	 * @var   string
	 */
	protected $minimumPHPVersion = '7.0.0';

	/**
	 * The minimum Joomla! version required to install this extension
	 *
	 * @var   string
	 */
	protected $minimumJoomlaVersion = '3.8.0';

	/**
	 * The maximum Joomla! version this extension can be installed on
	 *
	 * @var   string
	 */
	protected $maximumJoomlaVersion = '4.0.999';

	/**
	 * Obsolete files and folders to remove from both paid and free releases. This is used when you refactor code and
	 * some files inevitably become obsolete and need to be removed.
	 *
	 * @var   array
	 */
	protected $removeFilesAllVersions = array(
		'files'   => array(
		),
		'folders' => array(
		)
	);

	/**
	 * Runs on installation
	 *
	 * @param   JInstallerAdapterComponent $parent The parent object
	 *
	 * @return  void
	 */
	public function install($parent)
	{
		if (!defined('DATACOMPLIANCE_THIS_IS_INSTALLATION_FROM_SCRATCH'))
		{
			define('DATACOMPLIANCE_THIS_IS_INSTALLATION_FROM_SCRATCH', 1);
		}
	}

	/**
	 * Runs after install, update or discover_update
	 *
	 * @param   string                      $type install, update or discover_update
	 * @param   JInstallerAdapterComponent  $parent
	 *
	 * @return  boolean  True to let the installation proceed, false to halt the installation
	 */
	function postflight($type, $parent)
	{
		// Parent method
		parent::postflight($type, $parent);

		// Add ourselves to the list of extensions depending on Akeeba FEF
		$this->addDependency('file_fef', $this->componentName);

		return true;
	}

	/**
	 * Renders the post-installation message
	 */
	function renderPostInstallation($parent)
	{
		try
		{
			$this->warnAboutJSNPowerAdmin();
		}
		catch (Exception $e)
		{
			// Don't sweat if the site's db croaks while I'm checking for 3PD software that causes trouble
		}

		parent::renderPostInstallation($parent);
	}

	/**
	 * The PowerAdmin extension makes menu items disappear. People assume it's our fault. JSN PowerAdmin authors don't
	 * own up to their software's issue. I have no choice but to warn our users about the faulty third party software.
	 */
	private function warnAboutJSNPowerAdmin()
	{
		$db = JFactory::getDbo();

		$query = $db->getQuery(true)
		            ->select('COUNT(*)')
		            ->from($db->qn('#__extensions'))
		            ->where($db->qn('type') . ' = ' . $db->q('component'))
		            ->where($db->qn('element') . ' = ' . $db->q('com_poweradmin'))
		            ->where($db->qn('enabled') . ' = ' . $db->q('1'));
		$hasPowerAdmin = $db->setQuery($query)->loadResult();

		if (!$hasPowerAdmin)
		{
			return;
		}

		$query = $db->getQuery(true)
		            ->select('manifest_cache')
		            ->from($db->qn('#__extensions'))
		            ->where($db->qn('type') . ' = ' . $db->q('component'))
		            ->where($db->qn('element') . ' = ' . $db->q('com_poweradmin'))
		            ->where($db->qn('enabled') . ' = ' . $db->q('1'));
		$paramsJson = $db->setQuery($query)->loadResult();

		$className = class_exists('JRegistry') ? 'JRegistry' : '\Joomla\Registry\Registry';

		/** @var \Joomla\Registry\Registry $jsnPAManifest */
		$jsnPAManifest = new $className();
		$jsnPAManifest->loadString($paramsJson, 'JSON');
		$version = $jsnPAManifest->get('version', '0.0.0');

		if (version_compare($version, '2.1.2', 'ge'))
		{
			return;
		}

		echo <<< HTML
<div class="well" style="margin: 2em 0;">
<h1 style="font-size: 32pt; line-height: 120%; color: red; margin-bottom: 1em">WARNING: Menu items for {$this->componentName} might not be displayed on your site.</h1>
<p style="font-size: 18pt; line-height: 150%; margin-bottom: 1.5em">
	We have detected that you are using JSN PowerAdmin on your site. This software ignores Joomla! standards and
	<b>hides</b> the Component menu items to {$this->componentName} in the administrator backend of your site. Unfortunately we
	can't provide support for third party software. Please contact the developers of JSN PowerAdmin for support
	regarding this issue.
</p>
<p style="font-size: 18pt; line-height: 120%; color: green;">
	Tip: You can disable JSN PowerAdmin to see the menu items to {$this->componentName}.
</p>
</div>

HTML;

	}
}
