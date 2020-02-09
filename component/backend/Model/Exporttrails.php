<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Model\Mixin\FilterByUser;
use FOF30\Container\Container;
use FOF30\Model\DataModel;
use FOF30\Utils\Ip;
use JDatabaseQuery;

/**
 * Data export audit trails
 *
 * @property int    datacompliance_exporttrail_id Primary key
 * @property int    user_id                       The user for which the export was made
 * @property string created_on                    When the export was made
 * @property int    created_by                    Who created the export
 * @property string requester_ip                  The IP of the person who requested the export
 */
class Exporttrails extends DataModel
{
	use FilterByUser;

	/** @inheritDoc */
	public function __construct(Container $container, array $config = [])
	{
		parent::__construct($container, $config);

		$this->filterByUserField       = 'user_id';
		$this->filterByUserSearchField = 'user_id';
	}

	/**
	 * Checks the validity of the record. Also auto-fills the created* and requester_ip fields.
	 *
	 * @return  static
	 */
	public function check()
	{
		if (empty($this->user_id))
		{
			throw new \RuntimeException("Export audit trail: cannot have an empty user ID");
		}

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

	/**
	 * Executes before FOF builds the select query to retrieve model records
	 *
	 * @param   JDatabaseQuery  $query  The query object to modify
	 *
	 * @return  void
	 */
	protected function onBeforeBuildQuery(JDatabaseQuery &$query)
	{
		// Apply filtering by user. This is a relation filter, it needs to go before the main query builder fires.
		$this->filterByUser($query);
	}
}