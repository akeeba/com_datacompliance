<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Model;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

#[\AllowDynamicProperties]
class ConsenttrailsModel extends ListModel
{
	/** @inheritdoc */
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [];
		$config['filter_fields'] = $config['filter_fields'] ?: [
			'search', 'enabled',
		];

		parent::__construct($config, $factory);

		$this->filterFormName = 'filter_consenttrails';
	}

	/** @inheritdoc */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('a') . '.*',
				$db->quoteName('u.name'),
				$db->quoteName('u.username'),
				$db->quoteName('u.email'),
			])
			->from($db->quoteName('#__datacompliance_consenttrails', 'a'))
			->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.created_by'));

		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$id = (int) substr($search, 3);
				$query->where($db->quoteName('a.created_by') . ' = :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			elseif (stripos($search, 'email:') === 0)
			{
				$search = '%' . substr($search, 6) . '%';
				$query->where($db->quoteName('u.email') . ' LIKE :search')
					->bind(':search', $search);
			}
			elseif (stripos($search, 'name:') === 0)
			{
				$search = '%' . substr($search, 5) . '%';
				$query->where($db->quoteName('u.name') . ' LIKE :search')
					->bind(':search', $search);
			}
			elseif (stripos($search, 'username:') === 0)
			{
				$search = '%' . substr($search, 9) . '%';
				$query->where($db->quoteName('u.username') . ' LIKE :search')
					->bind(':search', $search);
			}
			else
			{
				$search = '%' . $search . '%';
				$query->where(
					'(' .
					$db->qn('u.name') . ' LIKE :search1' . ' OR ' .
					$db->qn('u.username') . ' LIKE :search2'
					. ')'
				)
					->bind(':search1', $search)
					->bind(':search2', $search);
			}
		}

		// Published filter
		$enabled = $this->getState('filter.enabled');
		if (is_numeric($enabled))
		{
			$query->where($db->quoteName('a.enabled') . ' = :enabled')
				->bind(':enabled', $enabled, ParameterType::INTEGER);
		}

		// List ordering clause
		$orderCol  = $this->state->get('list.ordering', 'created_on');
		$orderDirn = $this->state->get('list.direction', 'desc');
		$ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);

		$query->order($ordering);

		return $query;
	}

	/** @inheritdoc */
	protected function getStoreId($id = ''): string
	{
		// Compile the store id.
		$id .= ':' . $this->getState('filter.search');
		$id .= ':' . $this->getState('filter.enabled');

		return parent::getStoreId($id);
	}

	/**
	 * @inheritdoc
	 * @throws Exception
	 */
	protected function populateState($ordering = 'created_on', $direction = 'desc')
	{
		/** @var CMSApplication $app */
		$app = Factory::getApplication();

		$search = $app->getUserStateFromRequest($this->context . 'filter.search', 'filter_search', '', 'string');
		$this->setState('filter.search', $search);

		$enabled = $app->getUserStateFromRequest($this->context . 'filter.enabled', 'filter_enabled', '', 'string');
		$this->setState('filter.enabled', ($enabled === '') ? $enabled : (int) $enabled);

		parent::populateState($ordering, $direction);
	}
}