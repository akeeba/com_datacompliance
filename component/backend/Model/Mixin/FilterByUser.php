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
	protected function filterByUser(\JDatabaseQuery &$query, $searchField = null, $userField = null)
	{
		if (is_null($searchField))
		{
			$searchField = $this->filterByUserSearchField;
		}

		if (is_null($userField))
		{
			$userField = $this->filterByUserField;
		}

		// User search feature
		$search = $this->getState($searchField, null, 'string');

		if ($search)
		{
			// First get the Joomla! users fulfilling the criteria
			/** @var JoomlaUsers $users */
			$users = $this->container->factory->model('JoomlaUsers')->tmpInstance();
			$userIDs = $users->search($search)->with([])->get(true)->modelKeys();

			// If we were given an integer let's append it to the results
			if (is_numeric($search))
			{
				$userIDs[] = $search;
				asort($userIDs);
			}

			// If there are user IDs, we need to filter by them
			if (!empty($userIDs))
			{
				$query->where($query->qn($userField) . ' IN (' . implode(',', array_map(array($query, 'q'), $userIDs)) . ')');
			}
		}
	}
}