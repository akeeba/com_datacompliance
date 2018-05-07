<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model\Mixin;


trait FilterByUser
{
	protected $filterByUserField = 'created_by';
	protected $filterByUserSearchField = 'search';

	/**
	 * Apply select query filtering by username, email, business name or VAT / tax ID number
	 *
	 * @return  void
	 */
	protected function filterByUser(\JDatabaseQuery &$query)
	{
		// User search feature
		$search = $this->getState($this->filterByUserSearchField, null, 'string');

		if ($search)
		{
			// First get the Joomla! users fulfilling the criteria
			/** @var JoomlaUsers $users */
			$users = $this->container->factory->model('JoomlaUsers')->tmpInstance();
			$userIDs = $users->search($search)->with([])->get(true)->modelKeys();

			// If there are user IDs, we need to filter by them
			if (!empty($userIDs))
			{
				$query->where($query->qn($this->filterByUserField) . ' IN (' . implode(',', array_map(array($query, 'q'), $userIDs)) . ')');
			}
		}
	}
}