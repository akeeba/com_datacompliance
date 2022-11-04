<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
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
class UsertrailsModel extends ListModel
{
	/** @inheritdoc */
	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [];
		$config['filter_fields'] = $config['filter_fields'] ?: [
			'search',
		];

		parent::__construct($config, $factory);

		$this->filterFormName = 'filter_usertrails';
	}

	/** @inheritdoc */
	protected function getListQuery()
	{
		$db    = $this->getDatabase();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('a') . '.*',
				$db->quoteName('u.name', 'user_name'),
				$db->quoteName('u.username', 'user_username'),
				$db->quoteName('u.email', 'user_email'),
				$db->quoteName('c.name', 'creator_name'),
				$db->quoteName('c.username', 'creator_username'),
				$db->quoteName('c.email', 'creator_email'),
			])
			->from($db->quoteName('#__datacompliance_usertrails', 'a'))
			->join('LEFT', $db->quoteName('#__users', 'u'), $db->quoteName('u.id') . ' = ' . $db->quoteName('a.user_id'))
			->join('LEFT', $db->quoteName('#__users', 'c'), $db->quoteName('c.id') . ' = ' . $db->quoteName('a.created_by'));

		$search = $this->getState('filter.search');

		if (!empty($search))
		{
			if (stripos($search, 'id:') === 0)
			{
				$id = (int) substr($search, 3);
				$query->where($db->quoteName('a.datacompliance_usertrail_id') . ' = :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			elseif (stripos($search, 'user_id:') === 0)
			{
				$id = (int) substr($search, 8);
				$query->where($db->quoteName('a.user_id') . ' = :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			elseif (stripos($search, 'creator_id:') === 0)
			{
				$id = (int) substr($search, 8);
				$query->where($db->quoteName('a.created_by') . ' = :id')
					->bind(':id', $id, ParameterType::INTEGER);
			}
			elseif (stripos($search, 'email:') === 0)
			{
				$search = '%' . substr($search, 6) . '%';
				$query->extendWhere('AND', [
					$db->quoteName('u.email') . ' LIKE :search',
					$db->quoteName('c.email') . ' LIKE :search2',
				], 'OR')
					->bind(':search', $search)
					->bind(':search2', $search);
			}
			elseif (stripos($search, 'name:') === 0)
			{
				$search = '%' . substr($search, 5) . '%';
				$query->extendWhere('AND', [
					$db->quoteName('u.name') . ' LIKE :search',
					$db->quoteName('c.name') . ' LIKE :search2',
				], 'OR')
					->bind(':search', $search)
					->bind(':search2', $search);
			}
			elseif (stripos($search, 'username:') === 0)
			{
				$search = '%' . substr($search, 9) . '%';
				$query->extendWhere('AND', [
					$db->quoteName('u.username') . ' LIKE :search',
					$db->quoteName('c.username') . ' LIKE :search2',
				], 'OR')
					->bind(':search', $search)
					->bind(':search2', $search);
			}
			else
			{
				$search = '%' . $search . '%';
				$query->extendWhere('AND', [
					$db->quoteName('u.name') . ' LIKE :search',
					$db->quoteName('c.name') . ' LIKE :search2',
					$db->quoteName('u.username') . ' LIKE :search3',
					$db->quoteName('c.username') . ' LIKE :search4',
				], 'OR')
					->bind(':search', $search)
					->bind(':search2', $search)
					->bind(':search3', $search)
					->bind(':search4', $search);
			}
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

		parent::populateState($ordering, $direction);
	}
}