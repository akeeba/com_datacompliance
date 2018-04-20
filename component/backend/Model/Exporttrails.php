<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */


namespace Akeeba\DataCompliance\Admin\Model;

defined('_JEXEC') or die;

use FOF30\Model\DataModel;
use FOF30\Utils\Ip;

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
}