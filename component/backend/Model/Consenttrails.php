<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use FOF30\Container\Container;
use FOF30\Model\DataModel;
use FOF30\Utils\Ip;

/**
 * Consent audit trails
 *
 * @property   string  $created_on    When the consent was given / revoked
 * @property   int     $created_by    User consenting / revoking their consent
 * @property   string  $requester_ip  The IP of the person who requested the export
 * @property   int     $enabled       Was consent given?
 */
class Consenttrails extends DataModel
{
	public function __construct(Container $container, array $config = array())
	{
		$config['idFieldName'] = 'created_by';

		parent::__construct($container, $config);
	}

	/**
	 * Checks the validity of the record. Also auto-fills the created* and requester_ip fields.
	 *
	 * @return  static
	 */
	public function check()
	{
		if (empty($this->requester_ip))
		{
			if ($this->container->platform->isCli())
			{
				$this->requester_ip = '(CLI)';
			}
			else
			{
				$this->requester_ip = Ip::getIp();
			}
		}

		/** @var self $static This docblock is to keep phpStorm's static analysis from complaining */
		$static = parent::check();

		return $static;
	}

	protected function onBeforeBuildQuery(\JDatabaseQuery &$query)
	{
		// Apply filtering by user. This is a relation filter, it needs to go before the main query builder fires.
		$this->filterByUser($query);
	}

	/**
	 * Apply select query filtering by username, email, business name or VAT / tax ID number
	 *
	 * @return  void
	 */
	protected function filterByUser(\JDatabaseQuery &$query)
	{
		// User search feature
		$search = $this->getState('search', null, 'string');

		if ($search)
		{
			// First get the Joomla! users fulfilling the criteria
			/** @var JoomlaUsers $users */
			$users = $this->container->factory->model('JoomlaUsers')->tmpInstance();
			$userIDs = $users->search($search)->with([])->get(true)->modelKeys();

			// If there are user IDs, we need to filter by them
			if (!empty($userIDs))
			{
				$query->where($query->qn('created_by') . ' IN (' . implode(',', array_map(array($query, 'q'), $userIDs)) . ')');
			}
		}
	}

}