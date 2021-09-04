<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Table;

use Akeeba\Component\DataCompliance\Administrator\Mixin\AssertionAware;
use Akeeba\Component\DataCompliance\Administrator\Table\Mixin\CreateModifyAware;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Utilities\IpHelper;

defined('_JEXEC') or die;

/**
 * Consent audit trails
 *
 * @property   string $created_on    When the consent was given / revoked
 * @property   int    $created_by    User consenting / revoking their consent
 * @property   string $requester_ip  The IP of the person who requested the export
 * @property   int    $enabled       Was consent given?
 *
 * @since  3.0.0
 */
class ConsenttrailsTable extends AbstractTable
{
	use CreateModifyAware;
	use AssertionAware;

	public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
	{
		$this->_supportNullValue = false;
		$this->setColumnAlias('created', 'created_on');
		$this->setColumnAlias('id', 'created_by');

		parent::__construct('#__datacompliance_consenttrails', 'created_by', $db, $dispatcher);
	}

	protected function onBeforeCheck()
	{
		$this->requester_ip = $this->requester_ip ?: (IpHelper::getIp() ?: '(CLI)');
	}
}