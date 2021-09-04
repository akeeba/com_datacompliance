<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\DataCompliance\Administrator\Service\Html;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;

class DataCompliance
{
	use DatabaseAwareTrait;

	public function __construct(DatabaseDriver $db)
	{
		$this->setDbo($db);
	}
}