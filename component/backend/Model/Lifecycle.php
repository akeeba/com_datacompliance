<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;
use Joomla\CMS\Cache\Controller\CallbackController;

/**
 * Joomla users to be collected by the lifecycle policy
 *
 * @package  Akeeba\DataCompliance\Admin\Model
 */
class Lifecycle extends JoomlaUsers
{
	static $lifeCycleUserIDs = null;

	/**
	 * Build the SELECT query for returning records. Overridden to apply custom filters.
	 *
	 * @param   \JDatabaseQuery  $query           The query being built
	 * @param   bool             $overrideLimits  Should I be overriding the limit state (limitstart & limit)?
	 *
	 * @return  void
	 */
	public function onAfterBuildQuery(\JDatabaseQuery $query, $overrideLimits = false)
	{
		parent::onAfterBuildQuery($query, $overrideLimits);

		$lifecycle = $this->getState('lifecycle', true, 'bool');

		if ($lifecycle)
		{
			$lifecycleUserIDs = $this->getLifecycleUserIDs();
			$db               = $this->container->db;

			$query->where($db->qn('id') . ' IN (' . implode(',', $lifecycleUserIDs) . ')');
		}
	}

	/**
	 * Gets the user IDs of the expired user profiles. Goes through the cache for performance.
	 *
	 * @return  array
	 */
	public function getLifecycleUserIDs(): array
	{
		if (is_null(self::$lifeCycleUserIDs))
		{
			/** @var CallbackController $cache */
			$cache = \JFactory::getCache($this->container->componentName, 'callback');

			self::$lifeCycleUserIDs = $cache->get(function () {
				/** @var Wipe $mWipe */
				$mWipe = $this->container->factory->model('Wipe')->tmpInstance();

				return $mWipe->getLifecycleUserIDs(true);

			}, [], 'lifecycleUserIDs');
		}

		return self::$lifeCycleUserIDs;
	}

}