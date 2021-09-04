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
	use FilterByUser;

	/** @inheritDoc */
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