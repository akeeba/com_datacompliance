<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\ComponentParams;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\MVC\Model\BaseDatabaseModel as BaseDatabaseModelAlias;
use Joomla\CMS\Uri\Uri;

#[\AllowDynamicProperties]
class ControlpanelModel extends BaseDatabaseModelAlias
{
	/**
	 * Returns the number of active, deleted and expired users in Joomla
	 *
	 * @return  array  Keys 'active', 'deleted' and 'expired'
	 * @throws  Exception
	 *
	 * @since   1.0.0
	 */
	public function getUserStats(): array
	{
		$ret = [
			'active'  => 0,
			'deleted' => 0,
			'expired' => 0,
		];

		// Total number of users
		$db         = $this->getDatabase();
		$query      = $db->getQuery(true)
			->select('COUNT(' . $db->quoteName('id') . ')')
			->from($db->quoteName('#__users'));
		$totalUsers = $db->setQuery($query)->loadResult();

		// Lifecycle (inactive) users
		/** @var WipeModel $wipeModel */
		$wipeModel      = $this->getMVCFactory()->createModel('Wipe', 'Administrator', ['ignore_request' => true]);
		$lifeCycleUsers = $wipeModel->getLifecycleUserIDs(true);
		$wipedUsers     = $wipeModel->getWipedUserIDs();

		$ret['deleted'] = count($wipedUsers);
		$ret['expired'] = count($lifeCycleUsers);
		$ret['active']  = $totalUsers - $ret['expired'] - $ret['deleted'];

		return $ret;
	}

	/**
	 * Update the cached live site's URL for the front-end scheduling feature
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function updateMagicParameters(): void
	{
		$cParams = ComponentHelper::getParams('com_datacompliance');
		$cParams->set('siteurl', Uri::root(false));

		ComponentParams::save($cParams);
	}

}