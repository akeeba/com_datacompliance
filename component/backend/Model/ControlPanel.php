<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use FOF30\Database\Installer;
use FOF30\Model\Model;

class ControlPanel extends Model
{
	/**
	 * Checks the database for missing / outdated tables using the $dbChecks
	 * data and runs the appropriate SQL scripts if necessary.
	 *
	 * @throws  \RuntimeException    If the previous database update is stuck
	 *
	 * @return  $this
	 * @throws \Exception
	 */
	public function checkAndFixDatabase()
	{
		$params = $this->container->params;

		// First of all let's check if we are already updating
		$stuck = $params->get('updatedb', 0);

		if ($stuck)
		{
			throw new \RuntimeException('Previous database update is flagged as stuck');
		}

		// Then set the flag
		$params->set('updatedb', 1);
		$params->save();

		// Install or update database
		$db          = $this->container->db;
		$dbInstaller = new Installer($db, JPATH_ADMINISTRATOR . '/components/com_connection/sql/xml');

		$dbInstaller->updateSchema();

		// And finally remove the flag if everything went fine
		$params->set('updatedb', null);
		$params->save();

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
}
