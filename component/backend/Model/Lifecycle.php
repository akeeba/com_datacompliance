<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\DataCompliance\Admin\Model;

use DateTime;
use JDatabaseQuery;
use Joomla\CMS\Cache\Controller\CallbackController;

defined('_JEXEC') or die;

/**
 * Joomla users to be collected by the lifecycle policy
 *
 * @package  Akeeba\DataCompliance\Admin\Model
 */
class Lifecycle extends JoomlaUsers
{
	static $lifeCycleUserIDs = null;

	/**
	 * @inheritDoc
	 */
	public function onAfterBuildQuery(JDatabaseQuery $query, $overrideLimits = false)
	{
		parent::onAfterBuildQuery($query, $overrideLimits);

		$lifecycle = $this->getState('lifecycle', true, 'bool');

		if ($lifecycle)
		{
			$when             = $this->container->platform->getDate($this->getState('when', 'now', 'string'));
			$lifecycleUserIDs = $this->getLifecycleUserIDs($when);
			$db               = $this->container->db;

			if ($lifecycleUserIDs)
			{
				$query->where($db->qn('id') . ' IN (' . implode(',', $lifecycleUserIDs) . ')');
			}
		}

	}

	/**
	 * Gets the user IDs of the expired user profiles. Goes through the cache for performance.
	 *
	 * @param   DateTime  $when  return profiles which will be expired on or before the given date
	 *
	 * @return  array
	 */
	public function getLifecycleUserIDs(DateTime $when): array
	{
		if (is_null(self::$lifeCycleUserIDs))
		{
			/** @var CallbackController $cache */
			$cache = \JFactory::getCache($this->container->componentName, 'callback');

			self::$lifeCycleUserIDs = $cache->get(function () use ($when) {
				/** @var Wipe $mWipe */
				$mWipe = $this->container->factory->model('Wipe')->tmpInstance();

				return $mWipe->getLifecycleUserIDs(true, $when);

			}, [], 'lifecycleUserIDs');
		}

		return self::$lifeCycleUserIDs;
	}

}