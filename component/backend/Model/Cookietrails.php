<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Model\Mixin\FilterByUser;
use FOF40\Container\Container;
use FOF40\Model\DataModel;
use FOF40\IP\IPHelper as Ip;
use JDatabaseQuery;
use RuntimeException;

/**
 * Cookie consent preferences audit trails
 *
 * @property   int     datacompliance_cookietrail_id  Primary key.
 * @property   string  created_on                     When the changes were made.
 * @property   int     created_by                     Logged in user ID (0 = guest)
 * @property   string  requester_ip                   The IP of the person who performed the change.
 * @property   int     preference                     The recorded / applied cookie preference
 * @property   int     dnt                            The value of the DNT header. -1 = missing, -2 = was not taken into account
 * @property   int     reset                          1 if the preference was applied as a result of the user asking to reset their options
 *
 * @since      1.1.0
 */
class Cookietrails extends DataModel
{
	use FilterByUser;

	/** @inheritDoc */
	public function __construct(Container $container, array $config = array())
	{
		parent::__construct($container, $config);

		$this->filterByUserSearchField = 'created_by';
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

		if (is_null($this->preference))
		{
			throw new RuntimeException('The recorded preference cannot be null');
		}

		if (is_null($this->dnt))
		{
			$this->dnt = -2;
		}

		if (is_null($this->reset))
		{
			$this->reset = 0;
		}

		/** @var self $static This docblock is to keep phpStorm's static analysis from complaining */
		$static = parent::check();

		return $static;
	}

	/**
	 * Executes before FOF builds the select query to retrieve model records
	 *
	 * @param   JDatabaseQuery  $query  The query object to modify
	 *
	 * @return  void
	 */
	protected function onBeforeBuildQuery(JDatabaseQuery &$query): void
	{
		// Apply filtering by user. This is a relation filter, it needs to go before the main query builder fires.
		$this->filterByUser($query);
	}
}