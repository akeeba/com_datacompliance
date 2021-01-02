<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Exception;
use FOF30\Database\Installer;
use FOF30\Model\Model;
use RuntimeException;

class ControlPanel extends Model
{
	/**
	 * Checks the database for missing / outdated tables using the $dbChecks
	 * data and runs the appropriate SQL scripts if necessary.
	 *
	 * @return  $this
	 * @throws  RuntimeException    If the previous database update is stuck
	 *
	 * @throws  Exception
	 */
	public function checkAndFixDatabase()
	{
		// Install or update database
		$db          = $this->container->db;
		$dbInstaller = new Installer($db, JPATH_ADMINISTRATOR . '/components/com_datacompliance/sql/xml');

		$dbInstaller->updateSchema();

		return $this;
	}

	/**
	 * Update the cached live site's URL for the front-end scheduling feature
	 *
	 * @return  void
	 */
	public function updateMagicParameters()
	{
		$this->container->params->set('siteurl', str_replace('/administrator', '', \JUri::base()));
		$this->container->params->save();
	}

	/**
	 * Returns the number of active, deleted and expired users in Joomla
	 *
	 * @return  array  Keys 'active', 'deleted' and 'expired'
	 * @throws  Exception
	 */
	public function getUserStats()
	{
		$ret = [
			'active'  => 0,
			'deleted' => 0,
			'expired' => 0,
		];

		// Total number of users
		$db         = $this->container->db;
		$query      = $db->getQuery(true)
			->select('COUNT(id)')
			->from($db->qn('#__users'));
		$totalUsers = $db->setQuery($query)->loadResult();

		// Lifecycle (inactive) users
		/** @var Wipe $wipeModel */
		$wipeModel      = $this->container->factory->model('Wipe')->tmpInstance();
		$lifeCycleUsers = $wipeModel->getLifecycleUserIDs(true);
		$wipedUsers     = $wipeModel->getWipedUserIDs();

		$ret['deleted'] = count($wipedUsers);
		$ret['expired'] = count($lifeCycleUsers);
		$ret['active']  = $totalUsers - $ret['expired'] - $ret['deleted'];

		return $ret;
	}
}
