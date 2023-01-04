<?php
/**
 * @package   AkeebaDataCompliance
 * @copyright Copyright (c)2018-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Layout\LayoutHelper;

$displayData = [
	'textPrefix' => 'COM_DATACOMPLIANCE_WIPETRAILS',
	'formURL'    => 'index.php?option=com_datacompliance&view=wipetrails',
	//'helpURL'    => '',
	'icon'       => 'fa fa-user-minus',
	//'createURL'  => '',
];

echo LayoutHelper::render('joomla.content.emptystate', $displayData);
