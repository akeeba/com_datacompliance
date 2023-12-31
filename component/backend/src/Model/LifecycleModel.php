<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

#[\AllowDynamicProperties]
class LifecycleModel extends ListModel
{
	/** @inheritdoc */
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [];
		$config['filter_fields'] = $config['filter_fields'] ?: [
			'search', 'when', 'lifecycle',
			'id', 'name', 'registerDate', 'lastVisitDate'
		];

		parent::__construct($config, $factory);

		$this->filterFormName = 'filter_lifecycle';
	}

	/** @inheritdoc */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select('*')
			->from($db->quoteName('#__users'));

		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$id = (int) substr($search, 3);
				$query->where($db->quoteName('id') . ' = :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			elseif (stripos($search, 'email:') === 0)
			{
				$search = '%' . substr($search, 6) . '%';
				$query->extendWhere('AND', [
					$db->quoteName('email') . ' LIKE :search',
				], 'OR')
					->bind(':search', $search);
			}
			elseif (stripos($search, 'name:') === 0)
			{
				$search = '%' . substr($search, 5) . '%';
				$query->extendWhere('AND', [
					$db->quoteName('name') . ' LIKE :search',
				], 'OR')
					->bind(':search', $search);
			}
			elseif (stripos($search, 'username:') === 0)
			{
				$search = '%' . substr($search, 9) . '%';
				$query->extendWhere('AND', [
					$db->quoteName('username') . ' LIKE :search',
				], 'OR')
					->bind(':search', $search);
			}
			else
			{
				$search = '%' . $search . '%';
				$query->extendWhere('AND', [
					$db->quoteName('u.name') . ' LIKE :search',
					$db->quoteName('u.username') . ' LIKE :search2',
				], 'OR')
					->bind(':search', $search)
					->bind(':search2', $search);
			}
		}

		$lifecycle = $this->getState('filter.lifecycle', '');
		if (is_numeric($lifecycle))
		{
			$when             = clone Factory::getDate($this->getState('filter.when', 'now') ?: 'now');
			$lifecycleUserIDs = $this->getLifecycleUserIDs($when);

			if ($lifecycle == 1)
			{
				if (!empty($lifecycleUserIDs))
				{
					$query->whereIn($db->quoteName('id'), $lifecycleUserIDs);
				}
				else
				{
					// You cannot have a WHERE IN with an empty set, so we fake the intended result by returning nothing!
					$query->where('FALSE');
				}
			}
			elseif (!empty($lifecycleUserIDs))
			{
				$query->whereNotIn($db->quoteName('id'), $lifecycleUserIDs);
			}
		}

		// List ordering clause
		$orderCol  = $this->state->get('list.ordering', 'id');
		$orderDirn = $this->state->get('list.direction', 'asc');
		$ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);

		$query->order($ordering);

		return $query;
	}

	/** @inheritdoc */
	protected function getStoreId($id = ''): string
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');

		return parent::getStoreId($id);
	}

	/**
	 * @inheritdoc
	 * @throws Exception
	 */
	protected function populateState($ordering = 'id', $direction = 'asc')
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();

		$search = $app->getUserStateFromRequest($this->context . 'filter.search', 'filter_search', '', 'string');
		$this->setState('filter.search', $search);

		$when = $app->getUserStateFromRequest($this->context . 'filter.when', 'filter_when', '', 'string');
		$this->setState('filter.when', $when);

		$lifecycle = $app->getUserStateFromRequest($this->context . 'filter.lifecycle', 'filter_lifecycle', '', 'int');
		$this->setState('filter.lifecycle', is_numeric($lifecycle) ? (int)$lifecycle : '');

		parent::populateState($ordering, $direction);
	}

	/**
	 * Gets the user IDs of the expired user profiles. Goes through the cache for performance.
	 *
	 * @param   Date  $when  return profiles which will be expired on or before the given date
	 *
	 * @return  array
	 */
	public function getLifecycleUserIDs(Date $when): array
	{
		$options = ['defaultgroup' => 'com_datacompliance'];

		$cache = Factory::getContainer()->get(CacheControllerFactoryInterface::class)
			->createCacheController('callback', $options);

		return $cache->get(function () use ($when) {
			/** @var WipeModel $mWipe */
			$mWipe = $this->getMVCFactory()->createModel('Wipe');

			return $mWipe->getLifecycleUserIDs(true, $when);

		}, [], 'lifecycleUserIDs');
	}

}