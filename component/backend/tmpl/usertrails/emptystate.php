<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'textPrefix' => 'COM_DATACOMPLIANCE_USERTRAILS',
	'formURL'    => 'index.php?option=com_datacompliance&view=usertrails',
	//'helpURL'    => '',
	'icon'       => 'fa fa-users',
	//'createURL'  => '',
];

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
